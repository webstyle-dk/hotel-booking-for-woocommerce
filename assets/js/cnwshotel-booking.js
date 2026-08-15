/**
 * Frontend booking form interactions for WSH Hotel.
 *
 * @package CNWSHOTEL
 */

(function () {

	'use strict';

	function getLabels() {

		return window.cnwshotelBookingLabels || {

			staySingle: '',

			stayMultiple: '%d',

			currencyCode: '',

			decimalSeparator: '.',

			thousandSeparator: ',',

			priceDecimals: 2

		};

	}

	function replaceToken(template, value) {

		return String( template || '' ).replace( '%d', value ).replace( '%s', value );

	}

	function formatMoney(amount, labels) {

		var decimals = parseInt( labels.priceDecimals || '2', 10 );

		var decimalSeparator = labels.decimalSeparator || ',';

		var thousandSeparator = labels.thousandSeparator || '.';

		var currencyCode = labels.currencyCode || '';

		var fixed = Number( amount || 0 ).toFixed( decimals );

		var parts = fixed.split( '.' );

		parts[0] = parts[0].replace( /\B(?=(\d{3})+(?!\d))/g, thousandSeparator );

		return currencyCode ? parts.join( decimalSeparator ) + ' ' + currencyCode : parts.join( decimalSeparator );

	}

	function formatDate(date) {

		var year = date.getFullYear();

		var month = String( date.getMonth() + 1 ).padStart( 2, '0' );

		var day = String( date.getDate() ).padStart( 2, '0' );

		return year + '-' + month + '-' + day;

	}

	function setupDateFields(scope) {

		var checkin = scope.querySelector( '[name="cnwshotel_checkin"]' );

		var checkout = scope.querySelector( '[name="cnwshotel_checkout"]' );

		if ( ! checkin || ! checkout) {

			return;

		}

		function updateCheckout() {

			if ( ! checkin.value) {

				return;

			}

			var selected = new Date( checkin.value );

			if (isNaN( selected.getTime() )) {

				return;

			}

			var minDate = new Date( selected );

			minDate.setDate( minDate.getDate() + 1 );

			checkout.min = formatDate( minDate );

			if ( ! checkout.value || checkout.value <= checkin.value || checkout.value < checkout.min) {

				checkout.value = checkout.min;

			}

		}

		checkin.addEventListener( 'change', updateCheckout );

		updateCheckout();

	}

	function setupPriceCalculation(scope) {

		var priceData = scope.querySelector( '.cnwshotel_price_data' );

		var checkin = scope.querySelector( '[name="cnwshotel_checkin"]' );

		var checkout = scope.querySelector( '[name="cnwshotel_checkout"]' );

		var guests = scope.querySelector( '[name="cnwshotel_guests"]' );

		var stay = scope.querySelector( '.cnwshotel_stay' );

		var totalBox = scope.querySelector( '.cnwshotel_total' );

		if ( ! priceData || ! checkin || ! checkout) {

			return;

		}

		function calculate() {

			if ( ! checkin.value || ! checkout.value) {

				return;

			}

			var nights = (new Date( checkout.value ) - new Date( checkin.value )) / (1000 * 60 * 60 * 24);

			if (nights <= 0) {

				return;

			}

			var price = parseFloat( priceData.dataset.price || '0' );

			var pricingModel = priceData.dataset.pricingModel || 'per_room';

			var guestCount = guests ? Math.max( 1, parseInt( guests.value || '1', 10 ) ) : 1;

			var total = price * nights;

			if (pricingModel === 'per_person') {

				total = total * guestCount;

			}

			if (stay) {

				var labels = getLabels();

				stay.textContent = nights === 1 ? labels.staySingle : replaceToken( labels.stayMultiple, nights );

			}

			if (totalBox) {

				totalBox.textContent = formatMoney( total, getLabels() );

			}

		}

		checkin.addEventListener( 'change', calculate );

		checkout.addEventListener( 'change', calculate );

		if (guests) {

			guests.addEventListener( 'change', calculate );

			guests.addEventListener( 'input', calculate );

		}

		calculate();

	}

	function setupGallery(scope) {

		var thumbs = scope.querySelectorAll( '.cnwshotel_thumb' );

		var mainImage = scope.querySelector( '#cnwshotel_main_image' );

		if ( ! thumbs.length || ! mainImage) {

			return;

		}

		thumbs.forEach(
			function (thumb) {

				thumb.addEventListener(
					'click',
					function () {

						if (thumb.dataset.full) {

							mainImage.src = thumb.dataset.full;

						}

					}
				);

			}
		);

	}

	document.addEventListener(
		'DOMContentLoaded',
		function () {

			document.querySelectorAll( '.cnwshotel_search, .cnwshotel_single_room, .cnwshotel_booking_form' ).forEach(
				function (scope) {

					setupDateFields( scope );

					setupPriceCalculation( scope );

					setupGallery( scope );

				}
			);

		}
	);

}());
