<?php

namespace TLA_Media\GTM_Kit\Admin;

use TLA_Media\GTM_Kit\Options;

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
		<img src="<?php echo esc_url(GTMKIT_URL . 'assets/images/logo.svg'); ?>" width="140" height="54" alt="GTM Kit"/>
		<h1 id="gtmkit-title"><?php echo esc_html( get_admin_page_title() ); ?></h1>
		<div class="gtmkit_content_wrapper">
			<div class="gtmkit_content_cell" id="gtmkit_content_top">
		<?php
		if ( $form === true ) {

			echo '<form action="' . esc_url( admin_url( 'options.php' ) ) . '" method="post" id="gtmkit-conf" accept-charset="' . esc_attr( get_bloginfo( 'charset' ) ) . '" novalidate="novalidate">';

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
	 * @param string $type The option type
	 * @param string $variable The option variable
	 * @param string $label The option label
	 * @param array $field_data Optional setting field data
	 * @param string $description Optional description
	 */
	public function setting_row( string $type, string $variable, string $label, array $field_data = [], string $description = '' ): void {
		?>
		<div class="gtmkit-setting-row gtmkit-setting-row-<?php echo esc_html( $type); ?> gtmkit-clear">
			<div class="gtmkit-setting-label">
				<?php if ( $label ): ?>
					<label for="<?php echo esc_attr('gtmkit-setting-' . $variable ); ?>">
						<?php echo wp_kses( $label, 'code' ); ?>
					</label>
				<?php endif; ?>
			</div>
			<div class="gtmkit-setting-field">
				<?php $this->setting_field( $type, $variable, $field_data ) ?>
				<?php if ( ! empty( $description ) ): ?>
					<p class="desc">
						<?php echo wp_kses( $description, ['a' => [], 'br' => []] ); ?>
					</p>
				<?php endif; ?>
			</div>
		</div>
		<?php
	}

	/**
	 * Add setting row.
	 *
	 * @param array $data
	 */
	public function setting_field( string $type, string $variable, array $field_data = [] ): void {

		switch ( $type ) {
			case 'checkbox-toggle':
				$this->checkbox_toggle_field( $variable, $field_data );
				break;
			case 'text-input':
				$this->text_input_field( $variable, $field_data );
				break;
			case 'radio':
				$this->radio_fieldset( $variable, $field_data );
				break;
			case 'select':
				$this->select( $variable, $field_data );
				break;
		}

	}

	/**
	 * Generates the footer for admin pages.
	 *
	 * @param bool $save_button Whether a save button should be shown.
	 * @param bool $show_sidebar Whether to show the banner sidebar.
	 */
	public function admin_footer( bool $save_button = true, bool $show_sidebar = true ) {
		if ( $save_button ) {
			?>
			<div id="gtmkit-submit-container">
				<div id="gtmkit-submit-container-float" class="gtmkit-admin-submit">
					<?php submit_button( __( 'Save changes', 'gtmkit' ) ); ?>
				</div>

				<div id="gtmkit-submit-container-fixed" class="gtmkit-admin-submit gtmkit-admin-submit-fixed" style="display: none;">
					<?php submit_button( __( 'Save changes', 'gtmkit' ) ); ?>
				</div>
			</div>
			</form>
			<?php
		}

		?></div><!-- end of div gtmkit_content_top --><?php

		if ( $show_sidebar ) {
			?>
			<div id="sidebar-container" class="gtmkit_content_cell">
				<?php $this->admin_sidebar(); ?>
			</div>
			<?php
		}

		?>
			</div><!-- end of div gtmkit_content_wrapper -->
		</div><!-- end of wrap -->'
		<?php
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
	public function label( string $text, array $attribute ): void {
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

		echo "<label class='" . esc_attr( $attribute['class'] ) . "' for='" . esc_attr( $attribute['for'] ) . "'>". esc_html($text);
		if ( $attribute['close'] ) {
			echo '</label>';
		}

	}

	/**
	 * Output a legend element.
	 *
	 * @param string $text Legend text string.
	 * @param array $attribute HTML attributes set.
	 *
	 * @return string The HTML legend
	 */
	public function legend( string $text, array $attribute ): void {
		$defaults = [
			'id'    => '',
			'class' => '',
		];
		$attribute     = wp_parse_args( $attribute, $defaults );

		$id = ( $attribute['id'] === '' ) ? '' : $attribute['id'];

		echo '<legend class="gtmkit-form-legend ' . esc_attr( $attribute['class'] ) . '" id="' . esc_attr( $id ) . '">' . esc_html($text) . '</legend>';
	}

	/**
	 * Create a Checkbox input toggle.
	 *
	 * @param string $variable The variable within the option to create the checkbox for.
	 * @param array $attribute Extra attributes to add to the checkbox.
	 */
	public function checkbox_toggle_field( string $variable, array $field_data = [] ): void {

		$value = $this->get_field_value( $variable );

		$attributes = $field_data['attributes'] ?? [];

		$defaults = [
			'disabled' => false,
		];
		$attribute = wp_parse_args( $attributes, $defaults );

		if ( $value === true ) {
			$value = 'on';
		}

		$disabled_attribute = $this->get_disabled_attribute( $variable, $attribute );
		?>
		<label for="<?php echo esc_attr('gtmkit-setting-' . $variable ); ?>">
			<input
				type="checkbox"
				id="<?php echo esc_attr('gtmkit-setting-' . $variable ); ?>"
				name="<?php echo esc_attr( $this->option_name . '[' . $this->option_group . '][' . $variable . ']' ); ?>"
				value="on"
				<?php echo checked( $value, 'on', false ); ?>
				<?php echo esc_html( $disabled_attribute ); ?>
			/>
			<span class="gtmkit-setting-toggle-switch"></span>
			<span class="gtmkit-setting-toggle-checked-label"><?php esc_html_e( 'On', 'gtmkit' ); ?></span>
			<span class="gtmkit-setting-toggle-unchecked-label"><?php esc_html_e( 'Off', 'gtmkit' ); ?></span>
		</label>
		<?php
	}

	/**
	 * Create a Text input field.
	 *
	 * @param string $variable The variable within the option to create the text input field for.
	 * @param string $label The label to show for the variable.
	 * @param array $attribute Extra attributes to add to the input field. Can be class, disabled, autocomplete.
	 */
	public function text_input_field( string $variable, array $field_data = [] ): void {

		$attributes = $field_data['attributes'] ?? [];

		$defaults = [
			'placeholder' => '',
			'class'       => '',
		];
		$attribute = wp_parse_args( $attributes, $defaults );

		$value = $this->get_field_value( $variable, '' );

		$type = 'text';
		if ( isset( $attribute['type'] ) && $attribute['type'] === 'url' ) {
			$val  = urldecode( $value );
			$type = 'url';
		}
		$attributes = isset( $attribute['autocomplete'] ) ? ' autocomplete="' . esc_attr( $attribute['autocomplete'] ) . '"' : '';

		$disabled_attribute = $this->get_disabled_attribute( $variable, $attribute );

		$attributes
		?>
		<input
			<?php echo esc_attr( $attributes ); ?>
			class="textinput <?php echo esc_attr( $attribute['class'] ); ?>"
			placeholder="<?php echo esc_attr( $attribute['placeholder'] ); ?>"
			type="<?php echo esc_attr( $type ); ?>"
			id="<?php echo esc_attr( $variable ); ?>"
			name="<?php echo esc_attr( $this->option_name ) . '[' . esc_attr( $this->option_group ) . '][' . esc_attr( $variable ) . ']'; ?>"
			value="<?php echo esc_attr( $value ); ?>"
			<?php echo esc_html( $disabled_attribute ); ?>
		/><br class="clear" />
		<?php


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

		echo '<textarea cols="' . esc_attr( $attribute['cols'] ) . '" rows="' . esc_attr( $attribute['rows'] ) . '" class="textinput ' . esc_attr( $attribute['class'] ) . '" id="' . esc_attr( $variable ) . '" name="' . esc_attr( $this->option_name ) . '[' . esc_attr( $variable ) . ']"'. ' ' .esc_attr( $disabled_attribute ). '>' . esc_textarea( $val ) . '</textarea><br class="clear" />';
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
	public function select( string $variable, array $fieldset_data = [] ): void {

		if ( ! is_array( $fieldset_data['options'] ) || $fieldset_data['options'] === [] ) {
			return;
		}

		$defaults = [
			'disabled' => false,
			'attributes' => []
		];
		$fieldset_data     = wp_parse_args( $fieldset_data, $defaults );

		if ( $this->is_control_disabled( $variable )
			 || ( isset( $fieldset_data['disabled'] ) && $fieldset_data['disabled'] ) ) {
			$disabled = true;
		} else {
			$disabled = false;
		}

		$active_option = $this->get_field_value( $variable, '' );

		printf(
			'<select %s name="%s" id="%s">',
			($disabled) ? 'disabled=""' : 'class="select"',
			esc_attr( $this->option_name ) . '[' . esc_attr( $this->option_group ) . '][' . esc_attr( $variable ) . ']',
			esc_attr( $variable )
		);
		printf( '<option value="" %s>%s</option>', selected( $active_option, '', false ), esc_html__( '(not set)', 'gtmkit' ) );

		foreach ( $fieldset_data['options'] as $option_attribute_value => $option_html_value ) {
			printf(
				'<option value="%s" %s>%s</option>',
				esc_attr( $option_attribute_value ),
				selected( $active_option, $option_attribute_value, false ),
				esc_html( $option_html_value )
			);
		}

		echo "</select>";

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
	public function radio_fieldset( string $variable, array $fieldset_data = [] ): void {

		if ( ! is_array( $fieldset_data['options'] ) || $fieldset_data['options'] === [] ) {
			return;
		}

		$field_value = $this->get_field_value( $variable );

		$defaults = [
			'disabled' => false,
			'legend' => '',
			'legend_attr' => [],
			'line_break' => true,
			'attributes' => []
		];
		$fieldset_data     = wp_parse_args( $fieldset_data, $defaults );

		echo '<fieldset class="gtmkit-form-fieldset gtmkit_radio_block" id="' . esc_attr( $variable ) . '">';

		if ( $fieldset_data['legend'] ) {

			$legend_defaults = [
				'id'    => '',
				'class' => 'radiogroup',
			];

			$legend_attr = wp_parse_args( $fieldset_data['legend_attr'], $legend_defaults );

			$this->legend( $fieldset_data['legend'], $legend_attr );
		}

		foreach ( $fieldset_data['options'] as $key => $value ) {
			$option_label = $value;
			$aria_label  = '';

			if ( is_array( $value ) ) {
				$option_label = isset( $value['label'] ) ? $value['label'] : '';
				$aria_label  = isset( $value['aria_label'] ) ? $value['aria_label'] : '';
			}
			?>
			<input
				type="radio"
				class="radio"
				id="<?php echo esc_attr( $variable ); ?>-<?php echo esc_attr( $key ); ?>"
				name="<?php echo esc_attr( $this->option_name ); ?>[<?php echo esc_attr( $this->option_group ); ?>][<?php echo esc_attr( $variable ); ?>]"
				value="<?php echo esc_attr( $key ); ?>"
				<?php echo checked( $field_value, esc_attr( $key ), false ); ?>
				<?php echo esc_attr( $this->get_disabled_attribute( $variable, $fieldset_data['attributes'] ) ); ?>
			/>
			<?php

			$this->label(
				$option_label,
				[
					'for'        => esc_attr( $variable ) . '-' . esc_attr( $key ),
					'class'      => 'radio',
					'aria_label' => $aria_label,
				]
			);

			if ( $fieldset_data['line_break'] ) {
				echo '<br>';
			}
		}
		echo '</fieldset>';

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
