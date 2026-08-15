/**
 * Room editor admin interactions for WSH Hotel.
 *
 * @package CNWSHOTEL
 */

(function () {

	'use strict';

	function getLabels() {

		return window.cnwshotelRoomAdmin || {

			roomNumberPlaceholder: '',

			floorPlaceholder: '',

			active: '',

			inactive: '',

			removeRoom: '',

			removeConfirm: '',

			mediaTitle: '',

			mediaButton: '',

			mediaGalleryTitle: '',

			mediaGalleryButton: '',

			removeImage: ''

		};

	}

	function refreshUnitTotals() {

		var totalField = document.getElementById( 'cnwshotel_total_units_display' );

		if ( ! totalField) {

			return;

		}

		var total = 0;

		document.querySelectorAll( '.cnwshotel_room_unit' ).forEach(
			function (row) {

				var number = row.querySelector( 'input[name="room_number[]"]' );

				var status = row.querySelector( 'select[name="status[]"]' );

				if (number && number.value.trim() !== '' && ( ! status || status.value === 'active')) {

					total += 1;

				}

			}
		);

		totalField.value = total;

	}

	function buildUnitRow() {

		var labels = getLabels();

		var row = document.createElement( 'div' );

		var inner = document.createElement( 'div' );

		var number = document.createElement( 'input' );

		var floor = document.createElement( 'input' );

		var status = document.createElement( 'select' );

		var active = document.createElement( 'option' );

		var inactive = document.createElement( 'option' );

		var remove = document.createElement( 'button' );

		row.className = 'cnwshotel_room_unit';

		inner.className = 'cnwshotel_room_unit_row';

		number.type = 'text';

		number.name = 'room_number[]';

		number.className = 'widefat';

		number.placeholder = labels.roomNumberPlaceholder;

		floor.type = 'number';

		floor.name = 'floor[]';

		floor.className = 'widefat';

		floor.placeholder = labels.floorPlaceholder;

		status.name = 'status[]';

		status.className = 'widefat';

		active.value = 'active';

		active.textContent = labels.active;

		inactive.value = 'inactive';

		inactive.textContent = labels.inactive;

		remove.type = 'button';

		remove.className = 'button-link-delete cnwshotel_remove_room';

		remove.textContent = labels.removeRoom;

		status.appendChild( active );

		status.appendChild( inactive );

		inner.appendChild( number );

		inner.appendChild( floor );

		inner.appendChild( status );

		inner.appendChild( remove );

		row.appendChild( inner );

		return row;

	}

	function setupTabs() {

		document.querySelectorAll( '.cnwshotel_roomdata_wrap' ).forEach(
			function (wrap) {

				var buttons = wrap.querySelectorAll( '.cnwshotel_roomdata_tabs button' );

				var panels = wrap.querySelectorAll( '.cnwshotel_roomdata_panel' );

				buttons.forEach(
					function (button) {

						button.addEventListener(
							'click',
							function () {

								var target = button.getAttribute( 'data-tab' );

								buttons.forEach(
									function (item) {

										item.classList.remove( 'active' );

									}
								);

								panels.forEach(
									function (panel) {

										panel.classList.remove( 'active' );

									}
								);

								button.classList.add( 'active' );

								var activePanel = document.getElementById( target );

								if (activePanel) {

									activePanel.classList.add( 'active' );

								}

							}
						);

					}
				);

			}
		);

	}

	function setupUnits() {

		var addButton = document.getElementById( 'add-cnwshotel_room_unit' );

		var container = document.getElementById( 'cnwshotel_room_units_container' );

		if ( ! addButton || ! container) {

			return;

		}

		addButton.addEventListener(
			'click',
			function () {

				container.appendChild( buildUnitRow() );

				refreshUnitTotals();

			}
		);

		document.addEventListener(
			'click',
			function (event) {

				if ( ! event.target.classList.contains( 'cnwshotel_remove_room' )) {

					return;

				}

				event.preventDefault();

				if ( ! window.confirm( getLabels().removeConfirm )) {

					return;

				}

				var row = event.target.closest( '.cnwshotel_room_unit' );

				if (row) {

					row.remove();

					refreshUnitTotals();

				}

			}
		);

		document.addEventListener(
			'change',
			function (event) {

				if (

				event.target.matches( 'input[name="room_number[]"]' ) ||

				event.target.matches( 'select[name="status[]"]' )

				) {

					refreshUnitTotals();

				}

			}
		);

		refreshUnitTotals();

	}

	function setupWizard() {

		var wizard = document.querySelector( '.cnwshotel_wizard_form' );

		if ( ! wizard) {

			return;

		}

		var screens = Array.prototype.slice.call( wizard.querySelectorAll( '.cnwshotel_wizard_screen' ) );

		var steps = Array.prototype.slice.call( document.querySelectorAll( '.cnwshotel_wizard_step' ) );

		var prevButton = wizard.querySelector( '.cnwshotel_wizard_prev' );

		var nextButton = wizard.querySelector( '.cnwshotel_wizard_next' );

		var submitButton = wizard.querySelector( '.cnwshotel_wizard_submit' );

		var current = 0;

		function show(index) {

			current = Math.max( 0, Math.min( index, screens.length - 1 ) );

			screens.forEach(
				function (screen, screenIndex) {

					screen.classList.toggle( 'is-active', screenIndex === current );

				}
			);

			steps.forEach(
				function (step, stepIndex) {

					step.classList.toggle( 'is-active', stepIndex === current );

					step.classList.toggle( 'is-complete', stepIndex < current );

				}
			);

			if (prevButton) {

				prevButton.disabled = current === 0;

			}

			if (nextButton) {

				nextButton.style.display = current === screens.length - 1 ? 'none' : '';

			}

			if (submitButton) {

				submitButton.style.display = current === screens.length - 1 ? '' : 'none';

			}

		}

		function validateCurrent() {

			var required = screens[current].querySelectorAll( '[required]' );

			var valid = true;

			required.forEach(
				function (field) {

					if ( ! field.checkValidity()) {

						valid = false;

						field.reportValidity();

					}

				}
			);

			return valid;

		}

		if (prevButton) {

			prevButton.addEventListener(
				'click',
				function () {

					show( current - 1 );

				}
			);

		}

		if (nextButton) {

			nextButton.addEventListener(
				'click',
				function () {

					if (validateCurrent()) {

						show( current + 1 );

					}

				}
			);

		}

		show( 0 );

	}

	function setupWizardImagePicker() {

		var button = document.getElementById( 'cnwshotel_setup_select_image' );

		var field = document.getElementById( 'cnwshotel_setup_image_id' );

		var preview = document.getElementById( 'cnwshotel_setup_image_preview' );

		if ( ! button || ! field || ! preview || typeof window.wp === 'undefined' || ! window.wp.media) {

			return;

		}

		var frame;

		button.addEventListener(
			'click',
			function (event) {

				event.preventDefault();

				if (frame) {

					frame.open();

					return;

				}

				frame = window.wp.media(
					{

						title: getLabels().mediaTitle,

						button: {

							text: getLabels().mediaButton

						},

						multiple: false

					}
				);

				frame.on(
					'select',
					function () {

						var attachment = frame.state().get( 'selection' ).first().toJSON();

						var imageUrl = attachment.sizes && attachment.sizes.medium ? attachment.sizes.medium.url : attachment.url;

						field.value = attachment.id;

						preview.innerHTML = '';

						var image = document.createElement( 'img' );

						image.src = imageUrl;

						image.alt = '';

						preview.appendChild( image );

					}
				);

				frame.open();

			}
		);

	}

	document.addEventListener(
		'DOMContentLoaded',
		function () {

			setupTabs();

			setupUnits();

			setupWizard();

			setupWizardImagePicker();

		}
	);

}());
