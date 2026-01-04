import State from './State.mjs'

export default class Utils {

	// Determine the height when the element uses height:auto
	static detectRealHeight( $el ){
		// get the needed height
		const $_el = $el.clone().css( { height: 'auto' } ).insertBefore( $el ) // insertAfter doesn't work here - some glitch
		const realHeight = ($_el.css( 'box-sizing' ) === 'border-box') ? parseInt( $_el.css( 'height' ) ) : $_el.height()

		$_el.remove()

		return realHeight
	}

	// Set height explicitly
	static setHeight( $that, noanimation ){
		const newH = Utils.detectRealHeight( $that )

		// Animate to the target height
		if( !noanimation ){
			$that.css( { opacity: 0 } )
				.animate( { height: newH }, State.animSpeed, function(){
					jQuery( this ).animate( { opacity: 1 }, State.animSpeed * 1.5 )
				} )
		}
		else
			$that.css( { height: newH } )
	}

	// height limit
	static setAnswsMaxHeight( $that ){
		if( State.answMaxHeight === '-1' || State.answMaxHeight === '0' || !State.answMaxHeight ){
			return
		}

		const $el = $that.find( '.dem-vote, .dem-answers' ).first()
		const maxHeight = parseInt( State.answMaxHeight )

		$el.css( { 'max-height': 'none', 'overflow-y': 'visible' } ) // reset if set

		const elHeight = ($el.css( 'box-sizing' ) === 'border-box') ? parseInt( $el.css( 'height' ) ) : $el.height()

		// collapse if above max height and diff > 100px; hiding 100px isn't worth it
		const diff = elHeight - maxHeight
		if( diff > 100 ){
			$el.css( 'position', 'relative' )

			const $overlay = jQuery( '<span class="dem__collapser"><span class="arr"></span></span>' ).appendTo( $el )
			const fn__expand = function(){
				$overlay.addClass( 'expanded' ).removeClass( 'collapsed' )
			}
			const fn__collaps = function(){
				$overlay.addClass( 'collapsed' ).removeClass( 'expanded' )
			}
			let timeout

			// don't collapse if it was expanded
			if( $that.data( 'expanded' ) ){
				fn__expand()
			}
			else {
				fn__collaps()
				$el.height( maxHeight ).css( 'overflow-y', 'hidden' )
			}

			// trigger click on hover so user doesn't need to click to expand
			$overlay
				.on( 'mouseenter', function(){
					if( !$that.data( 'expanded' ) )
						timeout = setTimeout( function(){
							$overlay.trigger( 'click' )
						}, 1000 )
				} )
				.on( 'mouseleave', function(){
					clearTimeout( timeout )
				} )

			$overlay.on( 'click', function(){
				clearTimeout( timeout )

				// collapse
				if( $that.data( 'expanded' ) ){
					fn__collaps()

					$that.data( 'expanded', false )
					$that.height( 'auto' ) // let container move smoothly with content; restore height at the end
					$el.stop().css( 'overflow-y', 'hidden' ).animate( { height: maxHeight }, State.animSpeed, function(){
						Utils.setHeight( $that, true )
					} )
				}
				// expand
				else {
					fn__expand()

					// measure height without collapsing
					const newH = Utils.detectRealHeight( $el ) + 7 // extra space for "add your answer"

					$that.data( 'expanded', true )
					$that.height( 'auto' ) // let container move smoothly with content; restore height at the end
					$el.stop().animate( { height: newH }, State.animSpeed, function(){
						Utils.setHeight( $that, true )
						$el.css( 'overflow-y', 'visible' )

					} )
				}
			} )
		}

	}

	// max answers limit
	static maxAnswLimitInit(){

		State.$dems.on( 'change', 'input[type="checkbox"]', function(){
			const maxAnsws = jQuery( this ).closest( State.demmainsel ).data( 'opts' ).max_answs
			const $checkboxs = jQuery( this ).closest( State.demScreen ).find( 'input[type="checkbox"]' )
			const $checked = $checkboxs.filter( ':checked' ).length

			if( $checked >= maxAnsws ){
				$checkboxs.filter( ':not(:checked)' ).each( function(){
					jQuery( this ).prop( 'disabled', true ).closest( 'li' ).addClass( 'dem-disabled' )
				} )
			}
			else {
				$checkboxs.each( function(){
					jQuery( this ).prop( 'disabled', false ).closest( 'li' ).removeClass( 'dem-disabled' )
				} )
			}
		} )
	}

	static demShake( el ){
		const position = window.getComputedStyle( el ).position
		if( ! position || position === 'static' ){
			el.style.position = 'relative'
		}

		const keyframes = [
			{ left: '0px' },
			{ left: '-10px', offset: 0.2 },
			{ left: '10px', offset: 0.40 },
			{ left: '-10px', offset: 0.60 },
			{ left: '10px', offset: 0.80 },
			{ left: '0px', offset: 1 }
		]
		const timing = { duration: 500, iterations: 1, easing: 'linear' }
		el.animate( keyframes, timing )
	}

	// dots loading animation: ...
	static demLoadingDots( el ){
		const isInput = (el.tagName.toLowerCase() === 'input')
		const str = isInput ? el.value : el.innerHTML

		if( str.slice( -3 ) === '...' ){
			el[isInput ? 'value' : 'innerHTML'] = str.slice( 0, -3 )
		}
		else{
			el[isInput ? 'value' : 'innerHTML'] += '.'
		}

		State.loaderTm = setTimeout( () => Utils.demLoadingDots( el ), 200 )
	}

}
