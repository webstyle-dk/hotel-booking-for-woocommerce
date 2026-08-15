<?php
/**

 * Free admin menu and shell for WSH Hotel Booking Management.
 *
 * @package CNWSHOTEL
 */

if ( ! defined( 'ABSPATH' ) ) {

	exit;
}

/**
 * Registers and renders the free plugin admin screens.
 */
class CNWSHOTEL_Admin_Menu {

	/**

	 * Registers hooks.
	 */
	public function __construct() {

		add_action( 'admin_menu', array( $this, 'register_menu' ) );

		add_action( 'admin_init', array( $this, 'register_settings' ) );

		add_action( 'in_admin_header', array( $this, 'render_native_screen_topbar' ), 5 );
	}

	/**

	 * Registers options through the WordPress Settings API.
	 */
	public function register_settings() {

		register_setting(
			'cnwshotel_settings',
			'cnwshotel_cart_hold_minutes',
			array(

				'type'              => 'integer',

				'sanitize_callback' => array( $this, 'sanitize_cart_hold_minutes' ),

				'default'           => 20,

			)
		);
	}

	/**
	 * Sanitizes the free cart hold option.
	 * The Free plugin intentionally keeps cart holds fixed at 20 minutes. The
	 * value is still registered so the settings form uses the native WordPress
	 * Settings API and nonce flow.
	 *
	 * @param mixed $value Submitted value.
	 * @return int
	 */
	public function sanitize_cart_hold_minutes( $value ) {
		unset( $value );

		return 20;
	}

	/**
	 * Registers free plugin menu.
	 */
	public function register_menu() {

		add_menu_page(
			__( 'Hotel Booking', 'wsh-hotel' ),
			__( 'Hotel Booking', 'wsh-hotel' ),
			'manage_options',
			'cnwshotel-dashboard',
			array( $this, 'dashboard' ),
			'dashicons-calendar-alt',
			26
		);

		add_submenu_page(
			'cnwshotel-dashboard',
			__( 'Dashboard', 'wsh-hotel' ),
			__( 'Dashboard', 'wsh-hotel' ),
			'manage_options',
			'cnwshotel-dashboard',
			array( $this, 'dashboard' )
		);

		add_submenu_page(
			'cnwshotel-dashboard',
			__( 'Calendar', 'wsh-hotel' ),
			__( 'Calendar', 'wsh-hotel' ),
			'manage_options',
			'cnwshotel-calendar',
			array( $this, 'calendar' )
		);

		add_submenu_page(
			'cnwshotel-dashboard',
			__( 'Setup Wizard', 'wsh-hotel' ),
			__( 'Setup Wizard', 'wsh-hotel' ),
			'manage_options',
			'cnwshotel-setup-wizard',
			array( $this, 'setup_wizard' )
		);

		add_submenu_page(
			'cnwshotel-dashboard',
			__( 'Bookings', 'wsh-hotel' ),
			__( 'Bookings', 'wsh-hotel' ),
			'manage_options',
			'cnwshotel-bookings',
			array( $this, 'bookings' )
		);

		add_submenu_page(
			'cnwshotel-dashboard',
			__( 'Settings', 'wsh-hotel' ),
			__( 'Settings', 'wsh-hotel' ),
			'manage_options',
			'cnwshotel-settings',
			array( $this, 'settings' )
		);

		add_submenu_page(
			'cnwshotel-dashboard',
			__( 'Upgrade to Pro', 'wsh-hotel' ),
			__( 'Upgrade to Pro', 'wsh-hotel' ),
			'manage_options',
			'cnwshotel-upgrade-pro',
			array( $this, 'upgrade_pro' )
		);
	}

	/**
	 * CNWS Hotel submenu entries are intentionally not removed with remove_submenu_page().
	 * WordPress still needs the submenu registrations internally in order to allow
	 * direct access to admin.php?page=cnwshotel-... screens. The visual sidebar submenu
	 * is hidden with CSS only, so the CNWS Hotel topbar can be used without breaking page
	 * permissions.
	 */

	/**
	 * Renders dashboard.
	 */
	public function dashboard() {

		$this->shell_start( __( 'Dashboard', 'wsh-hotel' ), __( 'Simple single-unit booking for WordPress and WooCommerce.', 'wsh-hotel' ) );

		echo '<div class="cnwshotel_admin_grid">';

		$this->card(
			__( 'Setup Wizard', 'wsh-hotel' ),
			__( 'Create a new room and make sure the booking page is ready through a guided setup flow.', 'wsh-hotel' ),
			admin_url( 'admin.php?page=cnwshotel-setup-wizard' ),
			__( 'Open setup', 'wsh-hotel' )
		);

		$this->card(
			__( 'Room Types', 'wsh-hotel' ),
			__( 'Create rooms or units with a price, capacity and available inventory.', 'wsh-hotel' ),
			admin_url( 'edit.php?post_type=cnwshotel_room' ),
			__( 'Manage rooms', 'wsh-hotel' )
		);

		$this->card(
			__( 'Bookings', 'wsh-hotel' ),
			__( 'Review reservations created through the free booking flow.', 'wsh-hotel' ),
			admin_url( 'admin.php?page=cnwshotel-bookings' ),
			__( 'View bookings', 'wsh-hotel' )
		);

		$this->pro_notice_card();

		echo '</div>';

		$this->dashboard_booking_shortcode_card();
		$this->shell_end();
	}

	/**
	 * Renders the booking page and shortcode reference card on the dashboard.
	 */
	private function dashboard_booking_shortcode_card() {

		$booking_page_id = absint( get_option( 'cnwshotel_booking_page_id', 0 ) );

		$booking_page_url = $booking_page_id ? get_permalink( $booking_page_id ) : '';

		echo '<div class="cnwshotel_admin_card">';

		echo '<h2>' . esc_html__( 'Booking page and shortcodes', 'wsh-hotel' ) . '</h2>';

		if ( $booking_page_url ) {

			echo '<p>' . esc_html__( 'Your guests can search and book from this page:', 'wsh-hotel' ) . '</p>';

				echo '<p><a class="button button-primary" href="' . esc_url( $booking_page_url ) . '" target="_blank" rel="noopener noreferrer">' . esc_html__( 'Open booking page', 'wsh-hotel' ) . '</a></p>';

		} else {

			echo '<p>' . esc_html__( 'No booking page has been created yet. Use the Setup Wizard to create it automatically.', 'wsh-hotel' ) . '</p>';

			echo '<p><a class="button button-primary" href="' . esc_url( admin_url( 'admin.php?page=cnwshotel-setup-wizard' ) ) . '">' . esc_html__( 'Open setup', 'wsh-hotel' ) . '</a></p>';

		}

		echo '<p>' . esc_html__( 'Manual shortcodes are available under Settings > Shortcodes.', 'wsh-hotel' ) . '</p>';

		echo '<p><a class="button" href="' . esc_url(
			add_query_arg(
				array(
					'page' => 'cnwshotel-settings',
					'tab'  => 'shortcodes',
				),
				admin_url( 'admin.php' )
			)
		) . '">' . esc_html__( 'View shortcodes', 'wsh-hotel' ) . '</a></p>';

		echo '</div>';
	}

	/**
	 * Renders setup wizard.
	 */
	public function setup_wizard() {

		$this->shell_start( __( 'Setup Wizard', 'wsh-hotel' ), __( 'Create the first bookable room and the booking page without manual shortcode work.', 'wsh-hotel' ) );

		if ( class_exists( 'CNWSHOTEL_Setup_Wizard' ) ) {

			CNWSHOTEL_Setup_Wizard::render_page();

		}

		$this->shell_end();
	}

	/**
	 * Renders calendar page.
	 */
	public function calendar() {

		$this->shell_start( __( 'Calendar', 'wsh-hotel' ), __( 'Overview of room availability and reservations.', 'wsh-hotel' ) );

		if ( class_exists( 'CNWSHOTEL_Calendar' ) ) {

			$page = new CNWSHOTEL_Calendar();

			if ( method_exists( $page, 'render_page' ) ) {

				$page->render_page();

			} else {

				echo '<div class="cnwshotel_admin_card"><p>' . esc_html__( 'Calendar page could not load.', 'wsh-hotel' ) . '</p></div>';

			}
		} else {

					echo '<div class="cnwshotel_admin_card"><p>' . esc_html__( 'Calendar page could not load.', 'wsh-hotel' ) . '</p></div>';

		}

		$this->shell_end();
	}

	/**
	 * Renders bookings page.
	 */
	public function bookings() {

		$this->shell_start( __( 'Bookings', 'wsh-hotel' ), __( 'Reservations created through the free booking flow.', 'wsh-hotel' ) );

		if ( class_exists( 'CNWSHOTEL_Bookings_Admin' ) ) {

			$page = new CNWSHOTEL_Bookings_Admin();

			if ( method_exists( $page, 'render_page' ) ) {

				$page->render_page();

			} else {

				echo '<div class="cnwshotel_admin_card"><p>' . esc_html__( 'Bookings page could not load.', 'wsh-hotel' ) . '</p></div>';

			}
		} else {

					echo '<div class="cnwshotel_admin_card"><p>' . esc_html__( 'Bookings page could not load.', 'wsh-hotel' ) . '</p></div>';

		}

		$this->shell_end();
	}

	/**
	 * Renders settings.
	 */
	public function settings() {

		$saved = class_exists( 'CNWSHOTEL_Frontend_Request' ) ? CNWSHOTEL_Frontend_Request::get_settings_updated_flag() : false;

		if ( $saved ) {

			echo '<div class="notice notice-success is-dismissible"><p>' . esc_html__( 'Settings saved.', 'wsh-hotel' ) . '</p></div>';

		}

		$tab = class_exists( 'CNWSHOTEL_Frontend_Request' ) ? CNWSHOTEL_Frontend_Request::get_settings_tab() : 'booking';

		$this->shell_start( __( 'Settings', 'wsh-hotel' ), __( 'Free booking settings for the simple single-unit booking engine.', 'wsh-hotel' ) );

		echo '<nav class="cnwshotel_admin_tabs">';

		$this->tab_link( 'booking', __( 'Booking', 'wsh-hotel' ), $tab );

		$this->tab_link( 'shortcodes', __( 'Shortcodes', 'wsh-hotel' ), $tab );

		$this->tab_link( 'system', __( 'System', 'wsh-hotel' ), $tab );

		echo '</nav>';

		echo '<div class="cnwshotel_admin_card">';

		if ( 'booking' === $tab ) {

			echo '<h2>' . esc_html__( 'Booking settings', 'wsh-hotel' ) . '</h2>';

			echo '<p>' . esc_html__( 'WSH Hotel Free is built for simple single-unit bookings. Guests search by date and guest count, and book one available room or unit that matches the requested capacity.', 'wsh-hotel' ) . '</p>';

			echo '<form method="post" action="' . esc_url( admin_url( 'options.php' ) ) . '">';

			settings_fields( 'cnwshotel_settings' );

			echo '<table class="form-table" role="presentation">';

			echo '<tr>';

			echo '<th scope="row">' . esc_html__( 'Cart hold time', 'wsh-hotel' ) . '</th>';

			echo '<td>';

			echo '<strong>' . esc_html__( '20 minutes', 'wsh-hotel' ) . '</strong>';

			echo '<input type="hidden" name="cnwshotel_cart_hold_minutes" value="20">';

			echo '<p class="description">' . esc_html__( 'The free booking engine uses a fixed 20 minute cart hold to reduce double-booking while guests complete checkout.', 'wsh-hotel' ) . '</p>';

			echo '</td>';

			echo '</tr>';

			echo '<tr>';

			echo '<th scope="row">' . esc_html__( 'Booking mode', 'wsh-hotel' ) . '</th>';

			echo '<td>';

			echo '<strong>' . esc_html__( 'Simple single-unit booking', 'wsh-hotel' ) . '</strong>';

			echo '<p class="description">' . esc_html__( 'This mode books one available room or unit per checkout item.', 'wsh-hotel' ) . '</p>';

			echo '</td>';

			echo '</tr>';

			echo '</table>';

			submit_button( __( 'Save settings', 'wsh-hotel' ) );

			echo '</form>';

		}

		if ( 'shortcodes' === $tab ) {

			echo '<h2>' . esc_html__( 'Shortcodes', 'wsh-hotel' ) . '</h2>';

			echo '<p>' . esc_html__( 'Use these shortcodes if you want to place the free booking elements manually on a page.', 'wsh-hotel' ) . '</p>';

				echo '<table class="widefat striped">';

				echo '<thead><tr>';

				echo '<th>' . esc_html__( 'Function', 'wsh-hotel' ) . '</th>';

			echo '<th>' . esc_html__( 'Shortcode', 'wsh-hotel' ) . '</th>';

				echo '<th>' . esc_html__( 'Use', 'wsh-hotel' ) . '</th>';

			echo '</tr></thead><tbody>';

			echo '<tr><td>' . esc_html__( 'Search form', 'wsh-hotel' ) . '</td><td><code>[cnwshotel_search]</code></td><td>' . esc_html__( 'Shows the date and guest search form.', 'wsh-hotel' ) . '</td></tr>';

			echo '<tr><td>' . esc_html__( 'Room results', 'wsh-hotel' ) . '</td><td><code>[cnwshotel_room_results]</code></td><td>' . esc_html__( 'Shows matching rooms or units after a search.', 'wsh-hotel' ) . '</td></tr>';
				echo '<tr><td>' . esc_html__( 'Room overview', 'wsh-hotel' ) . '</td><td><code>[cnwshotel_rooms]</code></td><td>' . esc_html__( 'Shows all published rooms without search filtering.', 'wsh-hotel' ) . '</td></tr>';

			echo '</tbody></table>';

			$booking_page_id = absint( get_option( 'cnwshotel_booking_page_id', 0 ) );

			$booking_page_url = $booking_page_id ? get_permalink( $booking_page_id ) : '';

			if ( $booking_page_url ) {

				echo '<p class="cnwshotel_admin_muted">';

				echo esc_html__( 'Current booking page:', 'wsh-hotel' ) . ' ';

				echo '<a href="' . esc_url( $booking_page_url ) . '" target="_blank" rel="noopener noreferrer">' . esc_html( get_the_title( $booking_page_id ) ) . '</a>';

				echo '</p>';

			}
		}

		if ( 'system' === $tab ) {

			echo '<h2>' . esc_html__( 'System tools', 'wsh-hotel' ) . '</h2>';

			echo '<p>' . esc_html__( 'Use the setup wizard to create the booking page and prepare the free booking flow.', 'wsh-hotel' ) . '</p>';

			echo '<p><a class="button button-primary" href="' . esc_url( admin_url( 'admin.php?page=cnwshotel-setup-wizard' ) ) . '">' . esc_html__( 'Open Setup Wizard', 'wsh-hotel' ) . '</a></p>';

		}

		echo '</div>';

		$this->shell_end();
	}

	/**
	 * Renders the contextual Pro upgrade screen.
	 */
	public function upgrade_pro() {

		$this->shell_start( __( 'Upgrade to Pro', 'wsh-hotel' ), __( 'Need more booking tools? Pro is available as a separate add-on plugin.', 'wsh-hotel' ) );

		$this->render_pro_upgrade_content();

		$this->shell_end();
	}

	/**
	 * Renders a small contextual Pro notice card for the dashboard.
	 */
	private function pro_notice_card() {

		echo '<div class="cnwshotel_admin_card cnwshotel_pro_callout">';
		echo '<span class="cnwshotel_pro_badge">' . esc_html__( 'PRO', 'wsh-hotel' ) . '</span>';
		echo '<h2>' . esc_html__( 'Need multi-room bookings or hotel operations?', 'wsh-hotel' ) . '</h2>';
		echo '<p>' . esc_html__( 'WSH Hotel Free uses a simple booking engine. Multi-room allocation, rate plans, price rules, add-ons and advanced guest tools are available in the separate Pro add-on.', 'wsh-hotel' ) . '</p>';
		echo '<p><a class="button button-primary" href="' . esc_url( admin_url( 'admin.php?page=cnwshotel-upgrade-pro' ) ) . '">' . esc_html__( 'View Pro features', 'wsh-hotel' ) . '</a></p>';
		echo '</div>';
	}

	/**
	 * Renders compliant Free/Pro information without locked Pro functionality in the Free plugin.
	 */
	private function render_pro_upgrade_content() {

		if ( class_exists( 'CNWSHOTEL_Upgrade_Page' ) ) {
			CNWSHOTEL_Upgrade_Page::render();
			return;
		}

		echo '<div class="cnwshotel_admin_card cnwshotel_pro_callout">';
		echo '<span class="cnwshotel_pro_badge">' . esc_html__( 'PRO', 'wsh-hotel' ) . '</span>';
		echo '<h2>' . esc_html__( 'Upgrade when you need more control', 'wsh-hotel' ) . '</h2>';
		echo '<p>' . esc_html__( 'The free plugin does not contain locked Pro functionality and does not require a license key. Pro features are delivered through a separate add-on plugin outside WordPress.org.', 'wsh-hotel' ) . '</p>';
		echo '<p><a class="button button-primary" href="' . esc_url( 'https://web-style.dk/plugin/hotel-booking/' ) . '" target="_blank" rel="noopener noreferrer">' . esc_html__( 'Learn more about Pro', 'wsh-hotel' ) . '</a></p>';
		echo '</div>';
	}

	/**
	 * Renders topbar on native WordPress screens used by CNWS Hotel, such as Room Types.
	 */
	public function render_native_screen_topbar() {

		if ( ! $this->is_cnwshotel_native_screen() ) {

			return;

		}

		echo '<div class="cnwshotel_admin_native_shell">';

		$this->render_topbar();

		echo '</div>';
	}

	/**
	 * Starts CNWS Hotel admin shell.
	 *
	 * @param string $title Page title.
	 * @param string $subtitle Page subtitle.
	 */
	private function shell_start( $title, $subtitle = '' ) {

		echo '<div class="wrap cnwshotel_admin_shell">';

		$this->render_topbar();

		echo '<header class="cnwshotel_admin_header">';

		echo '<h1>' . esc_html( $title ) . '</h1>';

		if ( '' !== $subtitle ) {

			echo '<p>' . esc_html( $subtitle ) . '</p>';

		}

		echo '</header>';
	}

	/**
	 * Renders shared CNWS Hotel topbar.
	 */
	private function render_topbar() {

		$logo_url = $this->get_logo_url();

		echo '<div class="cnwshotel_admin_topbar">';

		echo '<div class="cnwshotel_admin_brand_wrap">';

		if ( $logo_url ) {

			echo '<div class="cnwshotel_admin_brand_logo">';

			echo '<img src="' . esc_url( $logo_url ) . '" alt="' . esc_attr__( 'Hotel Booking', 'wsh-hotel' ) . '">';

			echo '</div>';

		}

		echo '<div class="cnwshotel_admin_brand_text">';

		echo '<div class="cnwshotel_admin_brand">' . esc_html__( 'Hotel Booking', 'wsh-hotel' ) . '</div>';

		echo '<div class="cnwshotel_admin_subbrand">' . esc_html__( 'Free booking engine', 'wsh-hotel' ) . '</div>';

		echo '</div>';

		echo '</div>';

		echo '<nav class="cnwshotel_admin_topnav" aria-label="' . esc_attr__( 'WSH navigation', 'wsh-hotel' ) . '">';

		$this->topnav_link( admin_url( 'admin.php?page=cnwshotel-dashboard' ), __( 'Dashboard', 'wsh-hotel' ) );

		$this->topnav_link( admin_url( 'admin.php?page=cnwshotel-setup-wizard' ), __( 'Setup', 'wsh-hotel' ) );

		$this->topnav_link( admin_url( 'admin.php?page=cnwshotel-calendar' ), __( 'Calendar', 'wsh-hotel' ) );

		$this->topnav_link( admin_url( 'admin.php?page=cnwshotel-bookings' ), __( 'Bookings', 'wsh-hotel' ) );

		$this->topnav_link( admin_url( 'edit.php?post_type=cnwshotel_room' ), __( 'Rooms', 'wsh-hotel' ) );

		$this->topnav_link( admin_url( 'admin.php?page=cnwshotel-settings' ), __( 'Settings', 'wsh-hotel' ) );

		$this->topnav_link( admin_url( 'admin.php?page=cnwshotel-upgrade-pro' ), __( 'Pro', 'wsh-hotel' ) );
		echo '</nav>';

		echo '</div>';
	}

	/**
	 * Gets plugin logo URL for the admin topbar.
	 *
	 * @return string
	 */
	private function get_logo_url() {

		$logo_path = dirname( __DIR__ ) . '/assets/img/cnwshotel_logo.png';

		if ( ! file_exists( $logo_path ) ) {

			return '';

		}

		return plugins_url( 'assets/img/cnwshotel_logo.png', dirname( __DIR__ ) . '/wsh-hotel.php' );
	}

	/**
	 * Ends CNWS Hotel admin shell.
	 */
	private function shell_end() {

		echo '</div>';
	}

	/**
	 * Checks if current screen is a native WordPress screen owned by CNWS Hotel.
	 *
	 * @return bool
	 */
	private function is_cnwshotel_native_screen() {

		$screen = function_exists( 'get_current_screen' ) ? get_current_screen() : null;

		return $screen && isset( $screen->post_type, $screen->base ) && 'cnwshotel_room' === $screen->post_type && 'edit' === $screen->base;
	}

	/**
	 * Renders a top nav link.
	 *
	 * @param string $url   URL.
	 * @param string $label Label.
	 */
	private function topnav_link( $url, $label ) {

		echo '<a href="' . esc_url( $url ) . '">' . esc_html( $label ) . '</a>';
	}

	/**
	 * Renders settings tab link.
	 *
	 * @param string $slug Current slug.
	 * @param string $label Label.
	 * @param string $current Current tab.
	 */
	private function tab_link( $slug, $label, $current ) {

		$url = add_query_arg(
			array(
				'page' => 'cnwshotel-settings',
				'tab'  => $slug,
			),
			admin_url( 'admin.php' )
		);

		$active = $slug === $current ? ' is-active' : '';

		echo '<a class="cnwshotel_admin_tab' . esc_attr( $active ) . '" href="' . esc_url( $url ) . '">' . esc_html( $label ) . '</a>';
	}

	/**
	 * Renders dashboard card.
	 *
	 * @param string $title Card title.
	 * @param string $text Card text.
	 * @param string $url Button URL.
	 * @param string $button Button label.
	 */
	private function card( $title, $text, $url, $button ) {

		echo '<div class="cnwshotel_admin_card">';

		echo '<h2>' . esc_html( $title ) . '</h2>';

		echo '<p>' . esc_html( $text ) . '</p>';

		echo '<p><a class="button button-primary" href="' . esc_url( $url ) . '">' . esc_html( $button ) . '</a></p>';

		echo '</div>';
	}
}
