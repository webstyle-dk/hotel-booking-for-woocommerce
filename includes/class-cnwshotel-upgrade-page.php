<?php
/**
 * Contextual Free/Pro information page for WSH Hotel Free.
 *
 * @package CNWSHOTEL
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Renders the contextual Free/Pro information page.
 */
class CNWSHOTEL_Upgrade_Page {

	/**
	 * Renders the free plugin Pro information page.
	 */
	public static function render() {

		$pro_url        = 'https://web-style.dk/plugin/hotel-booking/';
		$hero_image_url = self::get_upgrade_image_url( 'cnwshotel_topbar_preview.jpg' );

		echo '<div class="cnwshotel_upgrade_page cnwshotel_upgrade_page_v2">';

		echo '<section class="cnwshotel_upgrade_hero_v2">';
		echo '<div class="cnwshotel_upgrade_hero_content">';
		echo '<span class="cnwshotel_upgrade_kicker">' . esc_html__( 'WSH Hotel Pro', 'wsh-hotel' ) . '</span>';
		echo '<h2>' . esc_html__( 'A more complete hotel workflow', 'wsh-hotel' ) . '</h2>';
		echo '<p>' . esc_html__( 'WSH Hotel Free is built for simple room bookings. The separate Pro add-on is delivered outside WordPress.org and expands the system with advanced operations, smarter pricing, integrations and tools for larger accommodation businesses.', 'wsh-hotel' ) . '</p>';
		echo '<div class="cnwshotel_upgrade_hero_actions">';
		echo '<a class="button button-primary button-hero" href="' . esc_url( $pro_url ) . '" target="_blank" rel="noopener noreferrer">' . esc_html__( 'View Pro features', 'wsh-hotel' ) . '</a>';
		echo '<a class="button button-secondary" href="#cnwshotel_free_vs_pro">' . esc_html__( 'Free vs Pro', 'wsh-hotel' ) . '</a>';
		echo '</div>';
		echo '</div>';
		echo '<div class="cnwshotel_upgrade_hero_preview" aria-hidden="true">';
		if ( $hero_image_url ) {
			echo '<div class="cnwshotel_upgrade_hero_image">';
			echo '<img src="' . esc_url( $hero_image_url ) . '" alt="">';
			echo '</div>';
		} else {
				echo '<div class="cnwshotel_preview_window">';
			echo '<div class="cnwshotel_preview_bar"><span></span><span></span><span></span></div>';
				echo '<div class="cnwshotel_preview_row is-blue"></div>';
			echo '<div class="cnwshotel_preview_row"></div>';
			echo '<div class="cnwshotel_preview_grid"><span></span><span></span><span></span><span></span></div>';
			echo '</div>';
		}
		echo '</div>';
		echo '</section>';

		echo '<section class="cnwshotel_upgrade_section">';
		echo '<div class="cnwshotel_upgrade_section_heading">';
		echo '<span>' . esc_html__( 'Highlighted features', 'wsh-hotel' ) . '</span>';
		echo '<h2>' . esc_html__( 'What Pro adds to the workflow', 'wsh-hotel' ) . '</h2>';
		echo '<p>' . esc_html__( 'Expand the booking engine with stronger operations, smarter sales and more advanced administration tools.', 'wsh-hotel' ) . '</p>';
		echo '</div>';

		echo '<div class="cnwshotel_upgrade_feature_grid">';
		self::feature_card(
			'cnwshotel_pro_integrations.jpg',
			__( 'Integrations and guest communication', 'wsh-hotel' ),
			__( 'Connect the booking workflow with external operators, automated sync and better guest communication.', 'wsh-hotel' ),
			__( 'Show more', 'wsh-hotel' ),
			array(
				__( 'Integration with third-party operators and booking channels.', 'wsh-hotel' ),
				__( 'Automatic sync for reservations and availability where supported by the Pro setup.', 'wsh-hotel' ),
				__( 'Guest email workflows for confirmations, booking updates and check-out information.', 'wsh-hotel' ),
				__( 'Extension options for treatments, court booking and other hotel or resort services.', 'wsh-hotel' ),
			)
		);
		self::feature_card(
			'cnwshotel_pro_pricing_finance.jpg',
			__( 'Pricing, finance and reporting', 'wsh-hotel' ),
			__( 'Use stronger data and pricing tools to support planning, revenue and daily decisions.', 'wsh-hotel' ),
			__( 'Show more', 'wsh-hotel' ),
			array(
				__( 'Reports for occupancy, KPI overview and planning insight.', 'wsh-hotel' ),
				__( 'Smart pricing with algorithm-based pricing options.', 'wsh-hotel' ),
				__( 'Finance integration options for a smoother reservation and payment workflow.', 'wsh-hotel' ),
				__( 'Better overview for optimization, planning and operational decisions.', 'wsh-hotel' ),
			)
		);
		self::feature_card(
			'cnwshotel_pro_operations.jpg',
			__( 'Operations and staff tools', 'wsh-hotel' ),
			__( 'Give staff practical tools for cleaning, breakfast, check-out and operational routines.', 'wsh-hotel' ),
			__( 'Show more', 'wsh-hotel' ),
			array(
				__( 'Tools for breakfast and cleaning to support a more efficient daily operation.', 'wsh-hotel' ),
				__( 'Device-friendly cleaning management for room readiness and staff follow-up.', 'wsh-hotel' ),
				__( 'Guest emails and an optimized check-out workflow.', 'wsh-hotel' ),
				__( 'Operational tools that help simplify and improve daily hotel workflows.', 'wsh-hotel' ),
			)
		);
		echo '</div>';
		echo '</section>';

		echo '<section class="cnwshotel_upgrade_section" id="cnwshotel_free_vs_pro">';
		echo '<div class="cnwshotel_upgrade_section_heading">';
		echo '<span>' . esc_html__( 'Comparison', 'wsh-hotel' ) . '</span>';
		echo '<h2>' . esc_html__( 'Free vs Pro', 'wsh-hotel' ) . '</h2>';
		echo '<p>' . esc_html__( 'Use Free for simple room bookings. Use Pro when the booking engine also needs to support operations, integrations and advanced hotel management.', 'wsh-hotel' ) . '</p>';
		echo '</div>';

		echo '<div class="cnwshotel_comparison_card">';
		echo '<table class="cnwshotel_comparison_table">';
		echo '<thead><tr>';
		echo '<th scope="col">' . esc_html__( 'Highlight feature', 'wsh-hotel' ) . '</th>';
		echo '<th scope="col">' . esc_html__( 'Free', 'wsh-hotel' ) . '</th>';
		echo '<th scope="col">' . esc_html__( 'Pro', 'wsh-hotel' ) . '</th>';
		echo '</tr></thead>';
		echo '<tbody>';
		self::comparison_row( __( 'Simple room or unit booking', 'wsh-hotel' ), true, __( 'Included', 'wsh-hotel' ), true, __( 'Included', 'wsh-hotel' ) );
		self::comparison_row( __( 'Unlimited room types and nights', 'wsh-hotel' ), true, __( 'Included', 'wsh-hotel' ), true, __( 'Included', 'wsh-hotel' ) );
		self::comparison_row( __( 'Multi-room booking flow', 'wsh-hotel' ), false, __( 'Not included', 'wsh-hotel' ), true, __( 'Included', 'wsh-hotel' ) );
		self::comparison_row( __( 'Hostel shared or private allocation', 'wsh-hotel' ), false, __( 'Not included', 'wsh-hotel' ), true, __( 'Included', 'wsh-hotel' ) );
		self::comparison_row( __( 'Add-ons and extra services', 'wsh-hotel' ), false, __( 'Not included', 'wsh-hotel' ), true, __( 'Included', 'wsh-hotel' ) );
		self::comparison_row( __( 'Breakfast management', 'wsh-hotel' ), false, __( 'Not included', 'wsh-hotel' ), true, __( 'Included', 'wsh-hotel' ) );
		self::comparison_row( __( 'Cleaning workflow', 'wsh-hotel' ), false, __( 'Not included', 'wsh-hotel' ), true, __( 'Included', 'wsh-hotel' ) );
		self::comparison_row( __( 'Integration with third-party operators', 'wsh-hotel' ), false, __( 'Not included', 'wsh-hotel' ), true, __( 'Included', 'wsh-hotel' ) );
		self::comparison_row( __( 'Price algorithm', 'wsh-hotel' ), false, __( 'Not included', 'wsh-hotel' ), true, __( 'Included', 'wsh-hotel' ) );
		self::comparison_row( __( 'Device app to manage cleaning', 'wsh-hotel' ), false, __( 'Not included', 'wsh-hotel' ), true, __( 'Included', 'wsh-hotel' ) );
		self::comparison_row( __( 'Finance integration', 'wsh-hotel' ), false, __( 'Not included', 'wsh-hotel' ), true, __( 'Included', 'wsh-hotel' ) );
		self::comparison_row( __( 'Email confirmation system', 'wsh-hotel' ), false, __( 'Not included', 'wsh-hotel' ), true, __( 'Included', 'wsh-hotel' ) );
		self::comparison_row( __( 'Own check-out system', 'wsh-hotel' ), false, __( 'Not included', 'wsh-hotel' ), true, __( 'Included', 'wsh-hotel' ) );
		echo '</tbody>';
		echo '</table>';
		echo '</div>';
		echo '</section>';

		echo '<section class="cnwshotel_upgrade_final_cta">';
		echo '<div>';
		echo '<span class="cnwshotel_upgrade_kicker">' . esc_html__( 'Ready for more control?', 'wsh-hotel' ) . '</span>';
		echo '<h2>' . esc_html__( 'Use Pro when bookings become daily operations', 'wsh-hotel' ) . '</h2>';
		echo '<p>' . esc_html__( 'When the property needs more than simple room bookings, the Pro add-on adds the workflow tools around reservations, staff and direct sales.', 'wsh-hotel' ) . '</p>';
		echo '</div>';
		echo '<a class="button button-primary button-hero" href="' . esc_url( $pro_url ) . '" target="_blank" rel="noopener noreferrer">' . esc_html__( 'Learn more about WSH Hotel Pro', 'wsh-hotel' ) . '</a>';
		echo '</section>';

		echo '</div>';
	}

	/**
	 * Renders a feature card with an expandable detail section.
	 *
	 * @param string                   $image_file  Image filename from assets/img.
	 * @param string                   $title       Card title.
	 * @param string                   $description Short description.
	 * @param string                   $summary     Details summary label.
	 * @param string|array<int,string> $details Expanded text or bullet list.
	 */
	private static function feature_card( $image_file, $title, $description, $summary, $details ) {

		$image_url = self::get_upgrade_image_url( $image_file );

		echo '<article class="cnwshotel_upgrade_feature_card">';
		if ( $image_url ) {
			echo '<div class="cnwshotel_pro_image">';
			echo '<img src="' . esc_url( $image_url ) . '" alt="">';
			echo '</div>';
		}
		echo '<div class="cnwshotel_upgrade_feature_body">';
		echo '<h3>' . esc_html( $title ) . '</h3>';
		echo '<p>' . esc_html( $description ) . '</p>';
		echo '<details class="cnwshotel_upgrade_details">';
		echo '<summary>' . esc_html( $summary ) . '</summary>';

		if ( is_array( $details ) ) {
			echo '<ul class="cnwshotel_upgrade_detail_list">';
			foreach ( $details as $detail ) {
				echo '<li>' . esc_html( $detail ) . '</li>';
			}
			echo '</ul>';
		} else {
			echo '<p>' . esc_html( $details ) . '</p>';
		}

		echo '</details>';
		echo '</div>';
		echo '</article>';
	}

	/**
	 * Gets upgrade feature image URL.
	 *
	 * @param string $image_file Image filename.
	 * @return string
	 */
	private static function get_upgrade_image_url( $image_file ) {

		$safe_file = sanitize_file_name( $image_file );
		$path      = dirname( __DIR__ ) . '/assets/img/' . $safe_file;

		if ( ! file_exists( $path ) ) {
			return '';
		}

		return plugins_url( 'assets/img/' . $safe_file, dirname( __DIR__ ) . '/wsh-hotel.php' );
	}

	/**
	 * Renders a comparison row.
	 *
	 * @param string $label       Feature label.
	 * @param bool   $free_status Whether free has the feature.
	 * @param string $free_text   Free description.
	 * @param bool   $pro_status  Whether pro has the feature.
	 * @param string $pro_text    Pro description.
	 */
	private static function comparison_row( $label, $free_status, $free_text, $pro_status, $pro_text ) {

		echo '<tr>';
		echo '<td class="cnwshotel_comparison_feature">' . esc_html( $label ) . '</td>';
		self::comparison_cell( $free_status, $free_text );
		self::comparison_cell( $pro_status, $pro_text );
		echo '</tr>';
	}

	/**
	 * Renders a comparison status cell.
	 *
	 * @param bool   $is_positive Whether the status is positive.
	 * @param string $text        Cell text.
	 */
	private static function comparison_cell( $is_positive, $text ) {

		$class = $is_positive ? 'is-positive' : 'is-negative';
		$mark  = $is_positive ? '+' : '-';
		$label = $is_positive ? __( 'Yes', 'wsh-hotel' ) : __( 'No', 'wsh-hotel' );

		echo '<td class="cnwshotel_comparison_status ' . esc_attr( $class ) . '">';
		echo '<span class="cnwshotel_status_mark" aria-label="' . esc_attr( $label ) . '">' . esc_html( $mark ) . '</span>';
		echo '<span>' . esc_html( $text ) . '</span>';
		echo '</td>';
	}
}
