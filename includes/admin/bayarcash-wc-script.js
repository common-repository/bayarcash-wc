(function($) {
    $(document).ready(function() {
        const paymentMethods = [
            'bayarcash-wc',
            'duitnow-wc',
            'linecredit-wc',
            'directdebit-wc',
            'duitnowqr-wc',
            'duitnowshopee-wc',
            'duitnowboost-wc',
            'duitnowqris-wc',
            'duitnowqriswallet-wc'
        ];
        const cache = {}; // Cache for jQuery selections and API responses

        // Use Promise.all for parallel initialization
        Promise.all(paymentMethods.map(setupVerifyToken))
            .then(() => {
                console.log('All payment methods initialized');
                setupAdditionalChargeFields();
            });

        async function setupVerifyToken(method) {
            const bearerTokenField = $(`textarea#woocommerce_${method}_bearer_token`);
            if (bearerTokenField.length === 0) return;

            const verifyDiv = $('<div>', {
                id: `${method}-verfiy-token`,
                html: `
                    <div id="${method}-vue-app">
                        <button type="button" class="button-primary" id="${method}-verify-button" @click="debouncedVerifyToken">Verify Token</button>
                        <span :id="'${method}-verify-status'" :class="{ valid: isTokenValid, invalid: !isTokenValid }" v-html="statusText"></span>
                    </div>
                `
            });
            bearerTokenField.after(verifyDiv);

            const app = createVueApp(method);
            app.mount(`#${method}-vue-app`);
        }

        function createVueApp(method) {
            return Vue.createApp({
                data() {
                    return {
                        method,
                        statusText: '',
                        status: null,
                        portalInfo: null,
                        savedSettings: {},
                        merchantName: ''
                    };
                },
                computed: {
                    isTokenValid() {
                        return this.status === 1;
                    },
                    filteredPortals() {
                        return this.portalInfo ? this.portalInfo.portals : [];
                    }
                },
                methods: {
                    async loadSavedSettings() {
                        if (cache[`settings_${this.method}`]) {
                            this.savedSettings = cache[`settings_${this.method}`];
                        } else {
                            try {
                                const response = await $.ajax({
                                    url: ajaxurl,
                                    method: 'POST',
                                    data: {
                                        action: 'get_bayarcash_settings',
                                        method: this.method
                                    }
                                });
                                this.savedSettings = JSON.parse(response);
                                cache[`settings_${this.method}`] = this.savedSettings;
                            } catch (error) {
                                console.error('Error loading saved settings:', error);
                            }
                        }
                        this.populateFields();
                    },
                    populateFields() {
                        const { bearer_token, portal_key } = this.savedSettings;
                        if (bearer_token) {
                            $(`textarea#woocommerce_${this.method}_bearer_token`).val(bearer_token);
                        }
                        if (portal_key) {
                            $(`select#woocommerce_${this.method}_portal_key`).val(portal_key);
                        }
                    },
                    async fetchAllPortals(apiUrl, token) {
                        let allPortals = [];
                        let nextPageUrl = apiUrl;
                        let merchantInfo = null;

                        while (nextPageUrl) {
                            try {
                                const response = await axios.get(nextPageUrl, {
                                    headers: {
                                        'Accept': 'application/json',
                                        'Authorization': `Bearer ${token}`
                                    }
                                });

                                if (response.status === 200) {
                                    const { data, meta, links } = response.data;
                                    allPortals = allPortals.concat(data);

                                    // Store merchant info from the first page
                                    if (!merchantInfo && meta.merchant) {
                                        merchantInfo = meta.merchant;
                                    }

                                    // Update nextPageUrl for the next iteration
                                    nextPageUrl = links.next;

                                    console.log(`Fetched page ${meta.current_page} of ${meta.last_page}`);
                                    this.setStatus(`Fetching data: page ${meta.current_page} of ${meta.last_page}`, null);
                                } else {
                                    throw new Error(`API request failed with status ${response.status}`);
                                }
                            } catch (error) {
                                console.error('Error fetching portals:', error);
                                break;
                            }
                        }

                        return { portals: allPortals, merchantInfo };
                    },
                    async verifyToken(event) {
                        if (event) event.preventDefault();

                        const token = $.trim($(`textarea#woocommerce_${this.method}_bearer_token`).val()) || '';
                        if (token === '') {
                            this.setStatus(`Please insert Token.`, 0);
                            this.clearPortalInfo();
                            this.clearFields();
                            return false;
                        }

                        const isSandboxMode = $(`input[type=checkbox]#woocommerce_${this.method}_sandbox_mode`).is(':checked');
                        const apiUrl = isSandboxMode ? 'https://console.bayarcash-sandbox.com/api/v2/portals' : 'https://console.bayar.cash/api/v2/portals';

                        this.setStatus(`Validating PAT token..`, null);

                        try {
                            const { portals, merchantInfo } = await this.fetchAllPortals(apiUrl, token);
                            this.handleApiResponse({ data: portals, meta: { merchant: merchantInfo } });
                        } catch (error) {
                            console.error('Error:', error);
                            this.handleInvalidToken();
                        }
                    },
                    debouncedVerifyToken: _.debounce(function() {
                        this.verifyToken();
                    }, 300),
                    setStatus(text, status) {
                        this.statusText = text + (status === 1 ? ' <span class="dashicons dashicons-yes-alt"></span>' :
                            status === 0 ? ' <span class="dashicons dashicons-dismiss"></span>' : '');
                        this.status = status;
                    },
                    handleApiResponse(response) {
                        const portalsList = response.data;
                        if (portalsList.length > 0) {
                            this.setStatus(`PAT Token is valid`, 1);
                            this.updatePortalInfo(response);
                            this.merchantName = response.meta.merchant ? response.meta.merchant.name : '';
                            this.displayMerchantName();
                            this.populatePortalKeyDropdown(portalsList);
                        } else {
                            this.handleInvalidToken();
                        }
                    },
                    updatePortalInfo(data) {
                        this.portalInfo = {
                            merchantName: data.meta.merchant ? data.meta.merchant.name : '',
                            portals: data.data
                        };
                    },
                    clearPortalInfo() {
                        this.portalInfo = null;
                        $('#portal-info').remove();
                    },
                    populatePortalKeyDropdown(portalsList) {
                        const selectElement = $(`select#woocommerce_${this.method}_portal_key`);
                        const currentValue = selectElement.val() || this.savedSettings.portal_key;
                        selectElement.empty();

                        if (portalsList.length === 0) {
                            selectElement.append($('<option>', { value: '', text: 'Select a portal' }));
                        } else {
                            portalsList.forEach(portal => {
                                selectElement.append($('<option>', {
                                    value: portal.portal_key,
                                    text: `${portal.portal_name} (${portal.portal_key})`
                                }));
                            });
                        }

                        if (currentValue && selectElement.find(`option[value="${currentValue}"]`).length > 0) {
                            selectElement.val(currentValue);
                        }
                    },
                    handleInvalidToken() {
                        this.setStatus(`Invalid PAT Token`, 0);
                        this.clearPortalInfo();
                        this.clearFields();

                        const selectElement = $(`select#woocommerce_${this.method}_portal_key`);
                        selectElement.empty().append($('<option>', {
                            value: '',
                            text: 'Please Insert Valid Token'
                        }));
                    },
                    clearFields() {
                        $(`select#woocommerce_${this.method}_portal_key`).val('');
                    },
                    displayMerchantName() {
                        const merchantNameElementId = `${this.method}-merchant-name`;
                        $(`#${merchantNameElementId}`).remove();
                        if (this.merchantName) {
                            const merchantNameElement = $('<div>', {
                                id: merchantNameElementId,
                                class: 'description',
                                html: `<strong>Merchant Name:</strong> ${this.merchantName}`,
                                css: {
                                    'margin-top': '10px',
                                    'margin-bottom': '10px'
                                }
                            });
                            $(`#${this.method}-verify-status`).after(merchantNameElement);
                        }
                    },
                    removeMerchantNameDisplay() {
                        $(`#${this.method}-merchant-name`).remove();
                    },
                },
                mounted() {
                    this.loadSavedSettings().then(() => {
                        const tokenField = $(`textarea#woocommerce_${this.method}_bearer_token`);
                        if (tokenField.val().trim() !== '') {
                            this.verifyToken();
                        }
                    });
                }
            });
        }

        function setupAdditionalChargeFields() {
            paymentMethods.forEach(method => {
                const chargeTypeField = $(`select#woocommerce_${method}_additional_charge_type`);
                const percentageField = $(`#woocommerce_${method}_additional_charge_percentage`).closest('tr');

                function updatePercentageFieldVisibility() {
                    const selected = chargeTypeField.val();
                    if (selected === 'both') {
                        percentageField.show();
                    } else {
                        percentageField.hide();
                    }
                }

                chargeTypeField.on('change', updatePercentageFieldVisibility);
                updatePercentageFieldVisibility(); // Initial setup
            });
        }
    });
})(jQuery);