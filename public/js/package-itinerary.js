( function () {
	'use strict';

	function setupSlider( slider ) {
		var track = slider.querySelector( '[data-hr-itinerary-track]' );
		var slides = Array.prototype.slice.call( slider.querySelectorAll( '[data-hr-itinerary-slide]' ) );
		var previous = slider.querySelector( '[data-hr-itinerary-previous]' );
		var next = slider.querySelector( '[data-hr-itinerary-next]' );
		var dots = slider.querySelector( '[data-hr-itinerary-dots]' );
		var index = 0;
		var maximumIndex = 0;
		var resizeFrame = 0;
		var touchStartX = null;

		if ( ! track || ! slides.length || ! previous || ! next || ! dots ) {
			return;
		}

		function getItemsPerView() {
			return window.matchMedia( '(max-width: 767px)' ).matches ? 1 : Math.min( 2, slides.length );
		}

		function buildDots() {
			var fragment = document.createDocumentFragment();

			dots.innerHTML = '';

			for ( var dotIndex = 0; dotIndex <= maximumIndex; dotIndex++ ) {
				( function ( targetIndex ) {
					var dot = document.createElement( 'button' );
					dot.type = 'button';
					dot.className = 'hr-package-itinerary__dot';
					dot.setAttribute( 'aria-label', 'Show image position ' + ( targetIndex + 1 ) );
					dot.addEventListener( 'click', function () {
						goTo( targetIndex );
					} );
					fragment.appendChild( dot );
				} )( dotIndex );
			}

			dots.appendChild( fragment );
		}

		function updateControls() {
			var dotButtons = dots.querySelectorAll( '.hr-package-itinerary__dot' );

			previous.disabled = 0 === index;
			next.disabled = index === maximumIndex;
			previous.hidden = 0 === maximumIndex;
			next.hidden = 0 === maximumIndex;
			dots.hidden = 0 === maximumIndex;

			Array.prototype.forEach.call( dotButtons, function ( dot, dotIndex ) {
				var active = dotIndex === index;
				dot.classList.toggle( 'is-active', active );
				dot.setAttribute( 'aria-current', active ? 'true' : 'false' );
			} );

			var itemsPerView = getItemsPerView();
			Array.prototype.forEach.call( slides, function ( slide, slideIndex ) {
				var visible = slideIndex >= index && slideIndex < index + itemsPerView;
				slide.setAttribute( 'aria-hidden', visible ? 'false' : 'true' );
			} );
		}

		function update( rebuildDots ) {
			if ( ! slider.offsetParent ) {
				return;
			}

			maximumIndex = Math.max( 0, slides.length - getItemsPerView() );
			index = Math.max( 0, Math.min( index, maximumIndex ) );

			if ( rebuildDots || dots.children.length !== maximumIndex + 1 ) {
				buildDots();
			}

			var firstOffset = slides[ 0 ].offsetLeft;
			var targetOffset = slides[ index ].offsetLeft - firstOffset;
			track.style.transform = 'translate3d(-' + targetOffset + 'px, 0, 0)';
			updateControls();
		}

		function goTo( targetIndex ) {
			index = Math.max( 0, Math.min( targetIndex, maximumIndex ) );
			update( false );
		}

		previous.addEventListener( 'click', function () {
			goTo( index - 1 );
		} );

		next.addEventListener( 'click', function () {
			goTo( index + 1 );
		} );

		slider.addEventListener( 'keydown', function ( event ) {
			if ( 'ArrowLeft' === event.key ) {
				event.preventDefault();
				goTo( index - 1 );
			} else if ( 'ArrowRight' === event.key ) {
				event.preventDefault();
				goTo( index + 1 );
			}
		} );

		slider.addEventListener( 'touchstart', function ( event ) {
			touchStartX = event.changedTouches.length ? event.changedTouches[ 0 ].clientX : null;
		}, { passive: true } );

		slider.addEventListener( 'touchend', function ( event ) {
			if ( null === touchStartX || ! event.changedTouches.length ) {
				return;
			}

			var distance = event.changedTouches[ 0 ].clientX - touchStartX;
			touchStartX = null;

			if ( Math.abs( distance ) < 40 ) {
				return;
			}

			goTo( distance > 0 ? index - 1 : index + 1 );
		}, { passive: true } );

		window.addEventListener( 'resize', function () {
			window.cancelAnimationFrame( resizeFrame );
			resizeFrame = window.requestAnimationFrame( function () {
				update( true );
			} );
		} );

		slider.hrPackageItineraryUpdate = function () {
			update( true );
		};

		update( true );
	}

	function setupItinerary( root ) {
		var toggles = Array.prototype.slice.call( root.querySelectorAll( '[data-hr-itinerary-toggle]' ) );
		var toggleAll = root.querySelector( '[data-hr-itinerary-toggle-all]' );
		var toggleAllLabel = root.querySelector( '[data-hr-itinerary-toggle-all-label]' );

		function syncToggleAll() {
			if ( ! toggleAll || ! toggleAllLabel ) {
				return;
			}

			var anyOpen = toggles.some( function ( toggle ) {
				return 'true' === toggle.getAttribute( 'aria-expanded' );
			} );

			toggleAll.setAttribute( 'aria-expanded', anyOpen ? 'true' : 'false' );
			toggleAllLabel.textContent = anyOpen ? 'Collapse All' : 'Expand All';
		}

		function setOpen( toggle, open ) {
			var panelId = toggle.getAttribute( 'aria-controls' );
			var panel = panelId ? document.getElementById( panelId ) : null;
			var day = toggle.closest( '[data-hr-itinerary-day]' );

			toggle.setAttribute( 'aria-expanded', open ? 'true' : 'false' );

			if ( day ) {
				day.classList.toggle( 'is-open', open );
			}

			if ( ! panel ) {
				return;
			}

			panel.hidden = ! open;

			if ( open ) {
				window.requestAnimationFrame( function () {
					Array.prototype.forEach.call( panel.querySelectorAll( '[data-hr-itinerary-slider]' ), function ( slider ) {
						if ( 'function' === typeof slider.hrPackageItineraryUpdate ) {
							slider.hrPackageItineraryUpdate();
						}
					} );
				} );
			}
		}

		toggles.forEach( function ( toggle ) {
			toggle.addEventListener( 'click', function () {
				setOpen( toggle, 'true' !== toggle.getAttribute( 'aria-expanded' ) );
				syncToggleAll();
			} );
		} );

		if ( toggleAll ) {
			toggleAll.addEventListener( 'click', function () {
				var anyOpen = toggles.some( function ( toggle ) {
					return 'true' === toggle.getAttribute( 'aria-expanded' );
				} );
				var openAll = ! anyOpen;

				toggles.forEach( function ( toggle ) {
					setOpen( toggle, openAll );
				} );
				syncToggleAll();
			} );
		}

		syncToggleAll();
	}

	function initialize() {
		Array.prototype.forEach.call( document.querySelectorAll( '[data-hr-itinerary-slider]' ), setupSlider );
		Array.prototype.forEach.call( document.querySelectorAll( '.hr-package-itinerary' ), setupItinerary );
	}

	if ( 'loading' === document.readyState ) {
		document.addEventListener( 'DOMContentLoaded', initialize );
	} else {
		initialize();
	}
}() );