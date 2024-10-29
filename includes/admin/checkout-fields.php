<div id="bayarcash-identification-fields">
	<h3><?php esc_html_e('Identification Info', 'bayarcash-wc'); ?></h3>
	<?php
	woocommerce_form_field(
		'bayarcash_identification_type',
		[
			'type'     => 'select',
			'required' => 'true',
			'label'    => esc_html__('Identification Type', 'bayarcash-wc'),
			'options'  => [
				'1' => 'New IC Number',
				'2' => 'Old IC Number',
				'3' => 'Passport Number',
				'4' => 'Business Registration',
			],
			'custom_attributes' => ['style' => 'padding: 14px 17px; font-size: 16px;'],
		],
		$checkout->get_value('bayarcash_identification_type')
	);

	woocommerce_form_field(
		'bayarcash_identification_id',
		[
			'type'        => 'text',
			'required'    => 'true',
			'label'       => esc_html__('Identification Number', 'bayarcash-wc'),
			'placeholder' => esc_html__('Identification Number', 'bayarcash-wc'),
		],
		$checkout->get_value('bayarcash_identification_id')
	);
	?>

</div>
