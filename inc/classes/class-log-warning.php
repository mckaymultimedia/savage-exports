<?php
/**
 * Logs Warning Messages on admin panel.
 *
 * @since      1.0.0
 *
 * @package    Savage-Exports
 * @subpackage inc/classes
 */

namespace Savage_Exports\Includes;

/**
 * Class to log warnings.
 */
class Log_Warning {
	/**
	 * Text message to be displayed in a warning.
	 *
	 * @var string
	 */
	private string $message;

	/**
	 * Message to be displayed before anchor tag.
	 *
	 * @var string
	 */
	private string $before_anchor_msg;

	/**
	 * Message to be displayed after anchor tag.
	 *
	 * @var string
	 */
	private string $after_anchor_msg;

	/**
	 * Anchor tag's url
	 *
	 * @var string
	 */
	private string $anchor_url;

	/**
	 * Text in anchor tag.
	 *
	 * @var string
	 */
	private string $anchor_text;

	/**
	 * Initialize class.
	 *
	 * @param string $text_message          text message to be displayed in a warning.
	 * @param string $before_anchor_message message to be displayed before anchor tag.
	 * @param string $anchor_url            anchor tag's url.
	 * @param string $anchor_text           text in anchor tag.
	 * @param string $after_anchor_message  message to be displayed after anchor tag.
	 */
	public function __construct(
		string $text_message,
		string $before_anchor_message = '',
		string $anchor_url = '',
		string $anchor_text = '',
		string $after_anchor_message = ''
	) {
		$this->message           = $text_message;
		$this->before_anchor_msg = $before_anchor_message;
		$this->after_anchor_msg  = $after_anchor_message;
		$this->anchor_url        = $anchor_url;
		$this->anchor_text       = $anchor_text;

		add_action( 'admin_notices', array( $this, 'render' ) );
	}

	/**
	 * Displays warning on the admin screen.
	 *
	 * @return void
	 */
	public function render(): void {
		printf(
			'<div class="notice notice-warning is-dismissible"><p>%s: %s %s <a href="%s">%s</a> %s </p></div>',
			esc_html__( 'Warning', 'savage-exports' ),
			esc_html( $this->message ),
			esc_html( $this->before_anchor_msg ),
			esc_url( $this->anchor_url ),
			esc_html( $this->anchor_text ),
			esc_html( $this->after_anchor_msg ),
		);
	}
}
