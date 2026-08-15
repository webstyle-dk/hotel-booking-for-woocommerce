/**
 * Room gallery media picker interactions for WSH Hotel.
 *
 * @package CNWSHOTEL
 */

jQuery(
	function ($) {
		'use strict';

		var frame;

		function getLabels() {
			return window.cnwshotelRoomAdmin || {
				mediaGalleryTitle: '',
				mediaGalleryButton: '',
				removeImage: ''
			};
		}

		function getGalleryIds(input) {
			try {
				return JSON.parse( input.val() || '[]' );
			} catch (err) {
				return [];
			}
		}

		function appendPreviewThumb(preview, file) {
			var thumb   = file && file.sizes && file.sizes.thumbnail ? file.sizes.thumbnail.url : file.url;
			var label   = getLabels().removeImage;
			var wrapper = $(
				'<div/>',
				{
					class: 'cnwshotel_admin_thumb',
					'data-id': parseInt( file.id, 10 ) || 0
				}
			);

			$(
				'<img/>',
				{
					src: thumb || '',
					alt: ''
				}
			).appendTo( wrapper );

			$(
				'<span/>',
				{
					class: 'remove',
					role: 'button',
					tabindex: 0,
					'aria-label': label,
					text: 'x'
				}
			).appendTo( wrapper );

			preview.append( wrapper );
		}

		$( document ).on(
			'click',
			'.cnwshotel_add_gallery',
			function (event) {
				event.preventDefault();

				var labels  = getLabels();
				var preview = $( '#cnwshotel_gallery_preview' );
				var input   = $( '#cnwshotel_room_gallery' );

				frame = wp.media(
					{
						title: labels.mediaGalleryTitle,
						button: { text: labels.mediaGalleryButton },
						multiple: true
					}
				);

				frame.on(
					'select',
					function () {
						var attachments = frame.state().get( 'selection' ).toJSON();
						var ids         = [];

						preview.empty();

						attachments.forEach(
							function (file) {
								var id = parseInt( file.id, 10 ) || 0;

								if ( ! id) {
									return;
								}

								ids.push( id );
								appendPreviewThumb( preview, file );
							}
						);

						input.val( JSON.stringify( ids ) );
					}
				);

				frame.open();
			}
		);

		$( document ).on(
			'click keydown',
			'.cnwshotel_admin_thumb .remove',
			function (event) {
				if ('keydown' === event.type && event.key !== 'Enter' && event.key !== ' ') {
					return;
				}

				event.preventDefault();

				var box   = $( this ).closest( '.cnwshotel_admin_thumb' );
				var id    = parseInt( box.data( 'id' ), 10 );
				var input = $( '#cnwshotel_room_gallery' );
				var ids   = getGalleryIds( input );

				box.remove();

				ids = ids.filter(
					function (item) {
						return parseInt( item, 10 ) !== id;
					}
				);

				input.val( JSON.stringify( ids ) );
			}
		);

		document.addEventListener(
			'DOMContentLoaded',
			function () {
				var main   = document.getElementById( 'cnwshotel_main_image' );
				var thumbs = document.querySelectorAll( '.cnwshotel_thumb' );
				var table  = document.querySelector( '.cnwshotel_availability_calendar table' );

				thumbs.forEach(
					function (thumb) {
						thumb.addEventListener(
							'click',
							function () {
								var newImage = this.getAttribute( 'data-full' );

								if (main && newImage) {
									main.src = newImage;
								}
							}
						);
					}
				);

				if (table) {
					table.querySelectorAll( 'tbody td' ).forEach(
						function (cell) {
							if (cell.cellIndex === 0) {
								return;
							}

							cell.addEventListener(
								'mouseenter',
								function () {
									var index = this.cellIndex;

									table.querySelectorAll( 'tr' ).forEach(
										function (row) {
											if (row.children[index]) {
												row.children[index].classList.add( 'highlight' );
											}
										}
									);
								}
							);

							cell.addEventListener(
								'mouseleave',
								function () {
									table.querySelectorAll( '.highlight' ).forEach(
										function (element) {
											element.classList.remove( 'highlight' );
										}
									);
								}
							);
						}
					);
				}

				document.querySelectorAll( '.floor-header' ).forEach(
					function (header) {
						header.addEventListener(
							'click',
							function () {
								var floor = this.dataset.floor;

								document.querySelectorAll( '.floor-' + floor ).forEach(
									function (room) {
										room.classList.toggle( 'hidden' );
									}
								);
							}
						);
					}
				);
			}
		);
	}
);
