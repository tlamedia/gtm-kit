<?php

namespace TLA\GTM_Kit\Admin;

use TLA\GTM_Kit\Options;

class OptionsForm {

	/**
	 * Instance of this class
	 *
	 * @var OptionsForm
	 */
	public static $instance;

	/**
	 * The short name of the option to use for the current page.
	 *
	 * @var string
	 */
	public $option_name;

	/**
	 * The option group.
	 *
	 * @var string
	 */
	public $option_group;

	/**
	 * Option instance.
	 *
	 * @var OptionsForm|null
	 */
	protected $option_instance = null;

	/**
	 * Get the singleton instance of this class.
	 *
	 * @return OptionsForm
	 */
	public static function get_instance(): OptionsForm {
		if ( ! ( self::$instance instanceof self ) ) {
			self::$instance = new self();
		}

		return self::$instance;
	}

	/**
	 * Generates the header for admin pages.
	 *
	 * @param bool $form Whether the form start tag should be included.
	 * @param string $option_name
	 * @param string $option_group
	 * @param string $settings_group
	 */
	public function admin_header( bool $form = true, string $option_name = 'gtmkit', string $option_group = 'general', string $settings_group = '' ): void {
		?>
	<div class="wrap gtmkit-admin-page <?php echo esc_attr( 'page-' . $option_group ); ?>">
		<img src="<?php echo GTMKIT_URL . 'assets/images/logo.svg'; ?>" width="140" height="54" alt="GTM Kit"/>
		<h1 id="gtmkit-title"><?php echo esc_html( get_admin_page_title() ); ?></h1>
		<div class="gtmkit_content_wrapper">
			<div class="gtmkit_content_cell" id="gtmkit_content_top">
		<?php
		if ( $form === true ) {

			printf(
				'<form action="%s" method="post" id="gtmkit-conf" accept-charset="%s" novalidate="novalidate">',
				esc_url( admin_url( 'options.php' ) ),
				esc_attr( get_bloginfo( 'charset' ) )
			);

			settings_fields( $settings_group );
		}

		$this->option_name = $option_name;
		$this->set_option_group( $option_group );
	}

	/**
	 * Set the option used in output for form elements.
	 *
	 * @param string $option_group Option group.
	 */
	public function set_option_group( string $option_group ) {
		$this->option_group = $option_group;
	}

	/**
	 * Add setting row.
	 *
	 * @param string $variable The option variable
	 * @param string $label The option label
	 * @param string $setting_field The setting field
	 * @param string $description Optional description
	 *
	 * @return string
	 */
	public function setting_row( string $variable, string $label, string $setting_field, string $description = '' ): string {
		$content = '<div class="gtmkit-setting-row gtmkit-setting-row-checkbox-toggle gtmkit-clear">';
		$content .= '<div class="gtmkit-setting-label">';

		if ( $label ) {
			$content .= '<label for="gtmkit-setting-' . esc_attr( $variable ) . '">' . $label . '</label>';
		}

		$content .= '</div>';
		$content .= '<div class="gtmkit-setting-field">';
		$content .= $setting_field;

		if ( ! empty( $description ) ) {
			$content .= '<p class="desc">' . $description . '</p>';
		}

		$content .= '</div>';
		$content .= '</div>';

		return $content;
	}

	/**
	 * Generates the footer for admin pages.
	 *
	 * @param bool $save_button Whether a save button should be shown.
	 * @param bool $show_sidebar Whether to show the banner sidebar.
	 */
	public function admin_footer( bool $save_button = true, bool $show_sidebar = true ) {
		if ( $save_button ) {
			echo '<div id="gtmkit-submit-container">';

			echo '<div id="gtmkit-submit-container-float" class="gtmkit-admin-submit">';
			submit_button( __( 'Save changes', 'gtmkit' ) );
			echo '</div>';

			echo '<div id="gtmkit-submit-container-fixed" class="gtmkit-admin-submit gtmkit-admin-submit-fixed" style="display: none;">';
			submit_button( __( 'Save changes', 'gtmkit' ) );
			echo '</div>';

			echo '</div>';

			echo '</form>';
		}

		echo '</div><!-- end of div gtmkit_content_top -->';

		if ( $show_sidebar ) {
			echo '<div id="sidebar-container" class="gtmkit_content_cell">';
			$this->admin_sidebar();
			echo '</div>';
		}

		echo '</div><!-- end of div gtmkit_content_wrapper -->';
		echo '</div><!-- end of wrap -->';
	}

	/**
	 * Generates the sidebar for admin pages.
	 */
	public function admin_sidebar() {
		include_once GTMKIT_PATH . 'src/views/admin-sidebar.php';
	}

	/**
	 * Output a label element.
	 *
	 * @param string $text Label text string.
	 * @param array $attribute HTML attributes set.
	 *
	 * @return string The HTML label
	 */
	public function label( string $text, array $attribute ): string {
		$defaults = [
			'class'      => 'checkbox',
			'close'      => true,
			'for'        => '',
			'aria_label' => '',
		];

		$attribute       = wp_parse_args( $attribute, $defaults );
		$aria_label = '';
		if ( $attribute['aria_label'] !== '' ) {
			$aria_label = ' aria-label="' . esc_attr( $attribute['aria_label'] ) . '"';
		}

		$output = "<label class='" . esc_attr( $attribute['class'] ) . "' for='" . esc_attr( $attribute['for'] ) . "'$aria_label>$text";
		if ( $attribute['close'] ) {
			$output .= '</label>';
		}

		return $output;
	}

	/**
	 * Output a legend element.
	 *
	 * @param string $text Legend text string.
	 * @param array $attribute HTML attributes set.
	 *
	 * @return string The HTML legend
	 */
	public function legend( string $text, array $attribute ): string {
		$defaults = [
			'id'    => '',
			'class' => '',
		];
		$attribute     = wp_parse_args( $attribute, $defaults );

		$id = ( $attribute['id'] === '' ) ? '' : ' id="' . esc_attr( $attribute['id'] ) . '"';

		return '<legend class="gtmkit-form-legend ' . esc_attr( $attribute['class'] ) . '"' . $id . '>' . $text . '</legend>';
	}

	/**
	 * Create a Checkbox input toggle.
	 *
	 * @param string $variable The variable within the option to create the checkbox for.
	 * @param string $label The label to show for the variable.
	 * @param string $description The label description.
	 * @param array $attribute Extra attributes to add to the checkbox.
	 */
	public function checkbox_toggle( string $variable, string $label, string $description = '', array $attribute = [] ): void {

		$val = $this->get_field_value( $variable );

		$defaults = [
			'disabled' => false,
		];
		$attribute     = wp_parse_args( $attribute, $defaults );

		if ( $val === true ) {
			$val = 'on';
		}

		$disabled_attribute = $this->get_disabled_attribute( $variable, $attribute );

		$setting_field = '<label for="gtmkit-setting-' . esc_attr( $variable ) . '">';
		$setting_field .= '<input type="checkbox" id="gtmkit-setting-' . esc_attr( $variable ) . '" name="' . esc_attr( $this->option_name ) . '[' . esc_attr( $this->option_group ) . '][' . esc_attr( $variable ) . ']" value="on"' . checked( $val, 'on', false ) . $disabled_attribute . '/>';
		$setting_field .= '<span class="gtmkit-setting-toggle-switch"></span>';
		$setting_field .= '<span class="gtmkit-setting-toggle-checked-label">' . __( 'On', 'gtmkit' ) . '</span>';
		$setting_field .= '<span class="gtmkit-setting-toggle-unchecked-label">' . __( 'Off', 'gtmkit' ) . '</span>';
		$setting_field .= '</label>';

		echo $this->setting_row( $variable, $label, $setting_field, $description );

	}

	/**
	 * Create a Text input field.
	 *
	 * @param string $variable The variable within the option to create the text input field for.
	 * @param string $label The label to show for the variable.
	 * @param array $attribute Extra attributes to add to the input field. Can be class, disabled, autocomplete.
	 */
	public function text_input( string $variable, string $label, array $attribute = [], string $description = '' ): void {
		$type = 'text';
		if ( ! is_array( $attribute ) ) {
			$attribute = [
				'class'    => $attribute,
				'disabled' => false,
			];
		}

		$defaults = [
			'placeholder' => '',
			'class'       => '',
		];
		$attribute     = wp_parse_args( $attribute, $defaults );
		$val      = $this->get_field_value( $variable, '' );
		if ( isset( $attribute['type'] ) && $attribute['type'] === 'url' ) {
			$val  = urldecode( $val );
			$type = 'url';
		}
		$attributes = isset( $attribute['autocomplete'] ) ? ' autocomplete="' . esc_attr( $attribute['autocomplete'] ) . '"' : '';

		$disabled_attribute = $this->get_disabled_attribute( $variable, $attribute );

		$setting_field = '<input' . $attributes . ' class="textinput ' . esc_attr( $attribute['class'] ) . '" placeholder="' . esc_attr( $attribute['placeholder'] ) . '" type="' . $type . '" id="' . esc_attr( $variable ) . '" name="' . esc_attr( $this->option_name ) . '[' . esc_attr( $this->option_group ) . '][' . esc_attr( $variable ) . ']" value="' . esc_attr( $val ) . '"' . $disabled_attribute . '/>' . '<br class="clear" />';

		echo $this->setting_row( $variable, $label, $setting_field, $description );
	}

	/**
	 * Create a textarea.
	 *
	 * @param string $variable The variable within the option to create the textarea for.
	 * @param string $label The label to show for the variable.
	 * @param string|array $attribute The CSS class or an array of attributes to assign to the textarea.
	 */
	public function textarea( string $variable, string $label, $attribute = [] ): void {
		if ( ! is_array( $attribute ) ) {
			$attribute = [
				'class' => $attribute,
			];
		}

		$defaults = [
			'cols'     => '',
			'rows'     => '',
			'class'    => '',
			'disabled' => false,
		];
		$attribute     = wp_parse_args( $attribute, $defaults );
		$val      = $this->get_field_value( $variable, '' );

		$this->label(
			$label,
			[
				'for'   => $variable,
				'class' => 'textinput',
			]
		);

		$disabled_attribute = $this->get_disabled_attribute( $variable, $attribute );

		echo '<textarea cols="' . esc_attr( $attribute['cols'] ) . '" rows="' . esc_attr( $attribute['rows'] ) . '" class="textinput ' . esc_attr( $attribute['class'] ) . '" id="' . esc_attr( $variable ) . '" name="' . esc_attr( $this->option_name ) . '[' . esc_attr( $variable ) . ']"', $disabled_attribute, '>' . esc_textarea( $val ) . '</textarea><br class="clear" />';
	}

	/**
	 * Create a hidden input field.
	 *
	 * @param string $variable The variable within the option to create the hidden input for.
	 * @param string $id The ID of the element.
	 * @param mixed $val Optional. The value to set in the input field. Otherwise, the value from the options will be used.
	 */
	public function hidden( string $variable, string $id = '', $val = null ): void {
		if ( is_null( $val ) ) {
			$val = $this->get_field_value( $variable, '' );
		}

		if ( is_bool( $val ) ) {
			$val = ( $val === true ) ? 'true' : 'false';
		}

		if ( $id === '' ) {
			$id = 'hidden_' . $variable;
		}

		echo '<input type="hidden" id="' . esc_attr( $id ) . '" name="' . esc_attr( $this->option_name ) . '[' . esc_attr( $variable ) . ']" value="' . esc_attr( $val ) . '"/>';
	}

	/**
	 * Create a Select Box.
	 *
	 * @param string $variable The variable within the option to create the select for.
	 * @param string $label The label to show for the variable.
	 * @param array $select_options The select options to choose from.
	 * @param bool $show_label Whether to show the label, if not, it will be applied as an aria-label.
	 * @param array $attribute Extra attributes to add to the select.
	 * @param string $help Optional. Inline Help HTML that will be printed after the label. Default is empty.
	 */
	public function select( string $variable, string $label, array $select_options, bool $show_label = true, array $attribute = [], string $help = '' ): void {

		$defaults = [
			'disabled' => false,
		];
		$attribute     = wp_parse_args( $attribute, $defaults );

		$select_attributes = 'class="select"';

		if ( $this->is_control_disabled( $variable )
			 || ( isset( $attribute['disabled'] ) && $attribute['disabled'] ) ) {
			$select_attributes = 'disabled=""';
		}

		if ( $show_label ) {
			$label = $this->label(
				$label,
				[
					'for'   => $variable,
					'class' => 'select',
				]
			);
		} else {
			$select_attributes .= ' aria-label="' . $label . '"';
			$label             = '';
		}

		$select_name = esc_attr( $this->option_name ) . '[' . esc_attr( $this->option_group ) . '][' . esc_attr( $variable ) . ']';

		$active_option = $this->get_field_value( $variable, '' );

		$setting_field = sprintf( '<select %s name="%s" id="%s">', $select_attributes, $select_name, $variable );
		$setting_field .= sprintf( '<option value="" %s>%s</option>', selected( $active_option, '', false ), __( '(not set)', 'gtmkit' ) );

		foreach ( $select_options as $option_attribute_value => $option_html_value ) {
			$setting_field .= sprintf( '<option value="%s" %s>%s</option>', $option_attribute_value, selected( $active_option, $option_attribute_value, false ), $option_html_value );

		}

		$setting_field .= "</select>";

		echo $this->setting_row( $variable, $label, $setting_field, $help );
	}

	/**
	 * Create a Radio input field.
	 *
	 * @param string $variable The variable within the option to create the radio button for.
	 * @param string $label The label to show for the field set.
	 * @param array $values The radio options to choose from.
	 * @param string $legend Optional. The legend to show for the field set, if any.
	 * @param array $legend_attr Optional. The attributes for the legend, if any.
	 * @param array $attribute Extra attributes to add to the radio button.
	 */
	public function radio( string $variable, string $label, array $values, string $legend = '', array $legend_attr = [], string $description = '', array $attribute = [], bool $lineBreak = true ): void {
		if ( ! is_array( $values ) || $values === [] ) {
			return;
		}
		$val = $this->get_field_value( $variable, false );

		$var_esc = esc_attr( $variable );

		$defaults = [
			'disabled' => false,
		];
		$attribute     = wp_parse_args( $attribute, $defaults );

		$setting_field = '<fieldset class="gtmkit-form-fieldset gtmkit_radio_block" id="' . $var_esc . '">';

		if ( is_string( $legend ) && $legend !== '' ) {

			$legend_defaults = [
				'id'    => '',
				'class' => 'radiogroup',
			];

			$legend_attr = wp_parse_args( $legend_attr, $legend_defaults );

			$setting_field .= $this->legend( $legend, $legend_attr );
		}

		foreach ( $values as $key => $value ) {
			$optionLabel = $value;
			$aria_label  = '';

			if ( is_array( $value ) ) {
				$optionLabel = isset( $value['label'] ) ? $value['label'] : '';
				$aria_label  = isset( $value['aria_label'] ) ? $value['aria_label'] : '';
			}

			$key_esc = esc_attr( $key );

			$disabled_attribute = $this->get_disabled_attribute( $variable, $attribute );

			$setting_field .= '<input type="radio" class="radio" id="' . $var_esc . '-' . $key_esc . '" name="' . esc_attr( $this->option_name ) . '[' . esc_attr( $this->option_group ) . '][' . $var_esc . ']" value="' . $key_esc . '" ' . checked( $val, $key_esc, false ) . $disabled_attribute . ' />';
			$setting_field .= $this->label(
				$optionLabel,
				[
					'for'        => $var_esc . '-' . $key_esc,
					'class'      => 'radio',
					'aria_label' => $aria_label,
				]
			);
			if ( $lineBreak ) {
				$setting_field .= '<br>';
			}
		}
		$setting_field .= '</fieldset>';

		echo $this->setting_row( $variable, $label, $setting_field, $description );

	}

	/**
	 * Retrieves the value for the form field.
	 *
	 * @param string $key The option key.
	 *
	 * @return mixed|null The retrieved value.
	 */
	protected function get_field_value( string $key ) {

		return Options::init()->get( $this->option_group, $key );
	}

	/**
	 * Checks whether a given control should be disabled.
	 *
	 * @param string $variable The variable within the option to check whether its control should be disabled.
	 *
	 * @return bool True if control should be disabled, false otherwise.
	 */
	protected function is_control_disabled( string $variable ): bool {
		if ( $this->option_instance === null ) {
			return false;
		}

		return $this->option_instance->is_disabled( $variable );
	}

	/**
	 * Returns the disabled attribute HTML.
	 *
	 * @param string $variable The variable within the option of the related form element.
	 * @param array $attribute Extra attributes added to the form element.
	 *
	 * @return string The disabled attribute HTML.
	 */
	protected function get_disabled_attribute( string $variable, array $attribute ): string {
		if ( $this->is_control_disabled( $variable ) || ( isset( $attribute['disabled'] ) && $attribute['disabled'] ) ) {
			return ' disabled';
		}

		return '';
	}
}
