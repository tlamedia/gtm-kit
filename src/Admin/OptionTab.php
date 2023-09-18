<?php
/**
 * GTM Kit plugin file.
 *
 * @package GTM Kit
 */

namespace TLA_Media\GTM_Kit\Admin;

/**
 * OptionTab
 */
final class OptionTab {

	/**
	 * Name of the tab.
	 *
	 * @var string
	 */
	private $name;

	/**
	 * Label of the tab.
	 *
	 * @var string
	 */
	private $label;

	/**
	 * Optional arguments.
	 *
	 * @var array
	 */
	private $arguments;

	/**
	 * Constructor.
	 *
	 * @param string $name Name of the tab.
	 * @param string $label Localized label of the tab.
	 * @param array  $arguments Optional arguments.
	 */
	public function __construct( string $name, string $label, array $arguments = [] ) {
		$this->name      = sanitize_title( $name );
		$this->label     = $label;
		$this->arguments = $arguments;
	}

	/**
	 * Gets the name.
	 *
	 * @return string The name.
	 */
	public function get_name(): string {
		return $this->name;
	}

	/**
	 * Gets the label.
	 *
	 * @return string The label.
	 */
	public function get_label(): string {
		return $this->label;
	}

	/**
	 * Gets the path of the view files.
	 *
	 * @return string The path.
	 */
	public function get_view_path(): string {
		return (string) $this->get_argument( 'view_path', GTMKIT_PATH . 'src/views/tabs/' );
	}

	/**
	 * Retrieves whether the tab needs a save button.
	 *
	 * @return bool Whether the tabs need a save button.
	 */
	public function has_save_button(): bool {
		return (bool) $this->get_argument( 'save_button', true );
	}

	/**
	 * Gets the option group.
	 *
	 * @return string The option group.
	 */
	public function get_opt_group(): string {
		return $this->get_argument( 'opt_group' );
	}

	/**
	 * Get tab data.
	 *
	 * @return array The tab data.
	 */
	public function get_tab_data(): array {
		return $this->get_argument( 'tab_data', [] );
	}

	/**
	 * Retrieves the variable from the supplied arguments.
	 *
	 * @param string       $variable Variable to retrieve.
	 * @param string|mixed $default_value Default to use when variable not found.
	 *
	 * @return mixed|string The retrieved variable.
	 */
	protected function get_argument( string $variable, $default_value = '' ) {
		return array_key_exists( $variable, $this->arguments ) ? $this->arguments[ $variable ] : $default_value;
	}
}
