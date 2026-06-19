/**
 * Integration registry for the "Viewing" filter.
 *
 * The canonical list of integrations the shell knows about, in display order,
 * with the labels the filter chips and notices use (the full product names,
 * which can differ from the shorter inline field tags). The chips are derived
 * per capability: only integrations that carry fields on that capability and
 * whose plugin is active are offered, so the filter never lists something the
 * page cannot act on.
 */
import { __ } from '@wordpress/i18n';
import { getAllFields } from './assemble';
import SettingsService from '../services/SettingsService';

const LABELS = {
	woocommerce: __( 'WooCommerce', 'gtm-kit' ),
	edd: __( 'Easy Digital Downloads', 'gtm-kit' ),
	cf7: __( 'Contact Form 7', 'gtm-kit' ),
	gf: __( 'Gravity Forms', 'gtm-kit' ),
};

const ORDER = [ 'woocommerce', 'edd', 'cf7', 'gf' ];

/**
 * The display label for an integration slug, or the slug itself when unknown.
 *
 * @param {string} slug Integration slug.
 * @return {string} The label.
 */
export const integrationLabel = ( slug ) => LABELS[ slug ] || slug;

/**
 * The active integrations that carry fields on a capability, in display order.
 *
 * @param {string} capabilityId Capability id.
 * @return {Array<{slug: string, label: string}>} Available integrations.
 */
export const getCapabilityIntegrations = ( capabilityId ) => {
	const present = new Set(
		getAllFields()
			.filter(
				( field ) =>
					field.capability === capabilityId &&
					field.integration &&
					SettingsService.isPluginActive( field.integration )
			)
			.map( ( field ) => field.integration )
	);

	return ORDER.filter( ( slug ) => present.has( slug ) ).map( ( slug ) => ( {
		slug,
		label: integrationLabel( slug ),
	} ) );
};

/**
 * The filter to actually apply on a capability: the active filter when it is
 * one of the capability's available integrations, otherwise none. This lets the
 * stored filter persist across capabilities without misapplying on a capability
 * that does not offer it.
 *
 * @param {string}      capabilityId Capability id.
 * @param {string|null} activeFilter The stored filter slug, or null.
 * @return {string|null} The effective filter.
 */
export const effectiveFilterFor = ( capabilityId, activeFilter ) =>
	activeFilter &&
	getCapabilityIntegrations( capabilityId ).some(
		( integration ) => integration.slug === activeFilter
	)
		? activeFilter
		: null;
