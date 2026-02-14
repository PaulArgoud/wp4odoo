<?php
declare( strict_types=1 );

/**
 * Plugin API Surface Contracts.
 *
 * Declarative manifest of all third-party plugin APIs that WP4Odoo depends on.
 * Each entry defines classes, functions, constants, and hooks that MUST exist
 * in the plugin source for our module to function correctly.
 *
 * Only plugins available on WordPress.org (free) are included.
 * Premium-only plugins (LearnDash, MemberPress, WC Subscriptions, WC Memberships,
 * RCP, AffiliateWP, Gravity Forms, WC Points & Rewards, WC Bookings, WC Bundles,
 * WP All Import Pro) are not testable automatically.
 *
 * @return array<string, array{
 *     slug: string,
 *     dir: string,
 *     module: string,
 *     classes: string[],
 *     functions: string[],
 *     constants: string[],
 *     actions: string[],
 * }>
 */
return [

	// ─── E-Commerce ──────────────────────────────────────────

	'woocommerce' => [
		'slug'      => 'woocommerce',
		'dir'       => 'woocommerce',
		'module'    => 'woocommerce',
		'classes'   => [
			'WooCommerce',
			'WC_Product',
			'WC_Product_Variable',
			'WC_Product_Variation',
			'WC_Product_Attribute',
			'WC_Order',
		],
		'functions' => [
			'wc_get_product',
			'wc_get_order',
			'wc_update_product_stock',
			'get_woocommerce_currency',
		],
		'constants' => [],
		'actions'   => [
			'woocommerce_update_product',
			'woocommerce_new_order',
			'woocommerce_order_status_changed',
		],
	],

	'easy-digital-downloads' => [
		'slug'      => 'easy-digital-downloads',
		'dir'       => 'easy-digital-downloads',
		'module'    => 'edd',
		'classes'   => [
			'Easy_Digital_Downloads',
			'EDD_Customer',
		],
		'functions' => [],
		'constants' => [],
		'actions'   => [
			'edd_update_payment_status',
		],
	],

	'ecwid' => [
		'slug'      => 'ecwid-shopping-cart',
		'dir'       => 'ecwid-shopping-cart',
		'module'    => 'ecwid',
		'classes'   => [],
		'functions' => [],
		'constants' => [ 'ECWID_PLUGIN_DIR' ],
		'actions'   => [],
	],

	// ─── Donations / Payments ────────────────────────────────

	'givewp' => [
		'slug'      => 'give',
		'dir'       => 'give',
		'module'    => 'givewp',
		'classes'   => [],
		'functions' => [],
		'constants' => [ 'GIVE_VERSION' ],
		'actions'   => [
			'give_update_payment_status',
		],
	],

	'charitable' => [
		'slug'      => 'charitable',
		'dir'       => 'charitable',
		'module'    => 'charitable',
		'classes'   => [ 'Charitable' ],
		'functions' => [],
		'constants' => [],
		'actions'   => [],
	],

	// ─── Membership ──────────────────────────────────────────

	'paid-memberships-pro' => [
		'slug'      => 'paid-memberships-pro',
		'dir'       => 'paid-memberships-pro',
		'module'    => 'pmpro',
		'classes'   => [ 'MemberOrder' ],
		'functions' => [ 'pmpro_getLevel' ],
		'constants' => [ 'PMPRO_VERSION' ],
		'actions'   => [
			'pmpro_save_membership_level',
			'pmpro_added_order',
			'pmpro_updated_order',
			'pmpro_after_change_membership_level',
		],
	],

	// ─── LMS ─────────────────────────────────────────────────

	'lifterlms' => [
		'slug'      => 'lifterlms',
		'dir'       => 'lifterlms',
		'module'    => 'lifterlms',
		'classes'   => [ 'LLMS_Order' ],
		'functions' => [ 'llms_get_student' ],
		'constants' => [ 'LLMS_VERSION' ],
		'actions'   => [
			'lifterlms_order_status_completed',
			'lifterlms_order_status_active',
			'llms_user_enrolled_in_course',
			'llms_user_removed_from_course',
		],
	],

	// ─── Events ──────────────────────────────────────────────

	'the-events-calendar' => [
		'slug'      => 'the-events-calendar',
		'dir'       => 'the-events-calendar',
		'module'    => 'events_calendar',
		'classes'   => [ 'Tribe__Events__Main' ],
		'functions' => [],
		'constants' => [],
		'actions'   => [],
	],

	'event-tickets' => [
		'slug'      => 'event-tickets',
		'dir'       => 'event-tickets',
		'module'    => 'events_calendar',
		'classes'   => [ 'Tribe__Tickets__Main' ],
		'functions' => [],
		'constants' => [],
		'actions'   => [
			'event_tickets_rsvp_ticket_created',
		],
	],

	// ─── Booking ─────────────────────────────────────────────

	'ameliabooking' => [
		'slug'      => 'ameliabooking',
		'dir'       => 'ameliabooking',
		'module'    => 'amelia',
		'classes'   => [],
		'functions' => [],
		'constants' => [ 'AMELIA_VERSION' ],
		'actions'   => [
			'amelia_after_appointment_booking_saved',
			'amelia_after_booking_canceled',
			'amelia_after_booking_rescheduled',
			'amelia_after_service_added',
			'amelia_after_service_updated',
		],
	],

	'bookly' => [
		'slug'      => 'bookly-responsive-appointment-booking-tool',
		'dir'       => 'bookly-responsive-appointment-booking-tool',
		'module'    => 'bookly',
		'classes'   => [ 'Plugin' ], // Bookly\Lib\Plugin — short name matches.
		'functions' => [],
		'constants' => [],
		'actions'   => [],
	],

	// ─── Recipes ─────────────────────────────────────────────

	'wp-recipe-maker' => [
		'slug'      => 'wp-recipe-maker',
		'dir'       => 'wp-recipe-maker',
		'module'    => 'wprm',
		'classes'   => [],
		'functions' => [],
		'constants' => [ 'WPRM_VERSION' ],
		'actions'   => [],
	],

	// ─── Forms ───────────────────────────────────────────────

	'contact-form-7' => [
		'slug'      => 'contact-form-7',
		'dir'       => 'contact-form-7',
		'module'    => 'forms',
		'classes'   => [ 'WPCF7_ContactForm', 'WPCF7_Submission' ],
		'functions' => [],
		'constants' => [ 'WPCF7_VERSION' ],
		'actions'   => [
			'wpcf7_mail_sent',
		],
	],

	'wpforms-lite' => [
		'slug'      => 'wpforms-lite',
		'dir'       => 'wpforms-lite',
		'module'    => 'forms',
		'classes'   => [],
		'functions' => [ 'wpforms' ],
		'constants' => [],
		'actions'   => [
			'wpforms_process_complete',
		],
	],

	'fluentform' => [
		'slug'      => 'fluentform',
		'dir'       => 'fluentform',
		'module'    => 'forms',
		'classes'   => [],
		'functions' => [],
		'constants' => [ 'FLUENTFORM' ],
		'actions'   => [
			'fluentform/submission_inserted',
		],
	],

	'formidable' => [
		'slug'      => 'formidable',
		'dir'       => 'formidable',
		'module'    => 'forms',
		'classes'   => [ 'FrmAppHelper', 'FrmField', 'FrmEntryMeta' ],
		'functions' => [],
		'constants' => [],
		'actions'   => [
			'frm_after_create_entry',
		],
	],

	'ninja-forms' => [
		'slug'      => 'ninja-forms',
		'dir'       => 'ninja-forms',
		'module'    => 'forms',
		'classes'   => [ 'Ninja_Forms' ],
		'functions' => [],
		'constants' => [],
		'actions'   => [
			'ninja_forms_after_submission',
		],
	],

	'forminator' => [
		'slug'      => 'forminator',
		'dir'       => 'forminator',
		'module'    => 'forms',
		'classes'   => [ 'Forminator_API' ],
		'functions' => [],
		'constants' => [ 'FORMINATOR_VERSION' ],
		'actions'   => [
			'forminator_custom_form_submit_before_set_fields',
		],
	],

	// ─── Invoicing ───────────────────────────────────────────

	'wp-invoice' => [
		'slug'      => 'wp-invoice',
		'dir'       => 'wp-invoice',
		'module'    => 'wp_invoice',
		'classes'   => [ 'WPI_Invoice' ],
		'functions' => [],
		'constants' => [],
		'actions'   => [
			'wpi_object_created',
			'wpi_object_updated',
			'wpi_successful_payment',
		],
	],

	// ─── Helpdesk ────────────────────────────────────────────

	'awesome-support' => [
		'slug'      => 'awesome-support',
		'dir'       => 'awesome-support',
		'module'    => 'awesome_support',
		'classes'   => [],
		'functions' => [],
		'constants' => [ 'WPAS_VERSION' ],
		'actions'   => [
			'wpas_open_ticket_after',
			'wpas_after_close_ticket',
			'wpas_after_reopen_ticket',
		],
	],

	'supportcandy' => [
		'slug'      => 'supportcandy',
		'dir'       => 'supportcandy',
		'module'    => 'supportcandy',
		'classes'   => [],
		'functions' => [],
		'constants' => [ 'WPSC_VERSION' ],
		'actions'   => [
			'wpsc_create_new_ticket',
			'wpsc_change_ticket_status',
		],
	],

	// ─── HR ──────────────────────────────────────────────────

	'wp-job-manager' => [
		'slug'      => 'wp-job-manager',
		'dir'       => 'wp-job-manager',
		'module'    => 'job_manager',
		'classes'   => [],
		'functions' => [],
		'constants' => [ 'JOB_MANAGER_VERSION' ],
		'actions'   => [],
	],

	// ─── Meta-modules ────────────────────────────────────────

	'advanced-custom-fields' => [
		'slug'      => 'advanced-custom-fields',
		'dir'       => 'advanced-custom-fields',
		'module'    => 'acf',
		'classes'   => [ 'ACF' ],
		'functions' => [ 'get_field', 'update_field' ],
		'constants' => [],
		'actions'   => [],
	],

	// ─── Crowdfunding ────────────────────────────────────────

	'wp-crowdfunding' => [
		'slug'      => 'wp-crowdfunding',
		'dir'       => 'wp-crowdfunding',
		'module'    => 'crowdfunding',
		'classes'   => [],
		'functions' => [ 'wpneo_crowdfunding_init' ],
		'constants' => [],
		'actions'   => [],
	],
];
