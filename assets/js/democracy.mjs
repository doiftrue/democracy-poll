import Utils from './Utils.mjs'
import State from './State.mjs'
import Loader from './Loader.mjs'
import Cache from './Cache.mjs'

document.addEventListener( 'DOMContentLoaded', democracyInit )

function democracyInit(){
	State.$polls = jQuery( State.mainSel )
	if( ! State.$polls.length ){
		return
	}

	State.$loader = document.querySelector( '.dem-loader' )

	const opts = State.$polls.first().data( 'opts' )
	State.ajaxurl = opts.ajax_url
	State.answMaxHeight = opts.answs_max_height
	State.animSpeed = parseInt( opts.anim_speed )
	State.lineAnimSpeed = parseInt( opts.line_anim_speed )

	queueMicrotask( init ) // wait for functions

	function init(){
		// Core Democracy events for all blocks
		const $demScreens = State.$polls.find( State.screenSel ).filter( ':visible' )
		const demScreensSetHeight = function(){
			$demScreens.each( function(){
				this.style.height = Utils.detectRealHeight( this ) + 'px'
			} )
		}

		$demScreens.demInitActions( 1 )

		window.addEventListener( 'resize', demScreensSetHeight ) // update height on resize

		window.addEventListener( 'load', demScreensSetHeight ) // update height once more

		Utils.maxAnswLimitInit() // limit for multi-answer selection

		/*
		 * Cache handling.
		 * Requires js-cookie to be installed
		 * and extra Democracy variables/methods.
		 */
		Cache.actionsHandler = ( screen, noanimation ) => jQuery( screen ).demInitActions( noanimation )
		Cache.initAll()
	}

	// Initialize all events for each poll: clicks, height, button visibility
	// applies to '.dem-screen'
	jQuery.fn.demInitActions = function( noanimation ){

		return this.each( function(){
			// Attach click handlers for all marked elements inside the given element:
			// includes AJAX on click and other Democracy interactions ----------
			const $this = jQuery( this )
			const attr = 'data-dem-act'

			$this.find( '[' + attr + ']' ).each( function(){
				const $the = jQuery( this )
				$the.attr( 'href', '' ) // clear URL so the request URL isn't visible

				$the.on( 'click', function( e ){
					e.preventDefault()
					$the.blur().demDoAction( $the.attr( attr ) )
				} )
			} )

			// Hide the submit button where needed ------------
			const autoVote = !! $this.find( 'input[type=radio][data-dem-act=vote]' ).first().length
			if( autoVote ) $this.find( '.dem-vote-button' ).hide()

			// collapse content if there are too many answers
			Utils.setAnswsMaxHeight( $this[0] )

			// animate filled bars - line_animation
			if( State.lineAnimSpeed ){
				$this.find( '.dem-fill' ).each( function(){
					const $fill = jQuery( this )
					//setTimeout(function(){ fill.style.width = was; }, State.animSpeed + 500); // based on CSS transition; also fires on reset and interferes...
					setTimeout( function(){
						$fill.animate( { width: $fill.data( 'width' ) }, State.lineAnimSpeed )
					}, State.animSpeed, 'linear' )
				} )
			}

			// Set height explicitly ------------
			// Bind to window resize (mobile rotation, etc.)
			Utils.setHeight( this, noanimation )

			// form submit event
			$this.find( 'form' ).on( 'submit', function( e ){
				e.preventDefault()

				const act = jQuery( this ).find( 'input[name="dem_act"]' ).val()
				if( act )
					jQuery( this ).demDoAction( jQuery( this ).find( 'input[name="dem_act"]' ).val() )
			} )
		} )
	}


	// Add user answer (link)
	jQuery.fn.demAddAnswer = function(){

		const $the = this.first()
		const $demScreen = $the.closest( State.screenSel )
		const isMultiple = $demScreen.find( '[type=checkbox]' ).length > 0
		const $input = jQuery( '<input type="text" class="' + State.userAnswerSel.replace( /\./, '' ) + '" value="">' ) // input for adding an answer

		// show vote button
		$demScreen.find( '.dem-vote-button' ).show()

		// handle radio inputs: uncheck and attach click handler
		$demScreen.find( '[type=radio]' ).each( function(){

			jQuery( this ).on( 'click', function(){
				$the.fadeIn( 300 )
				jQuery( State.userAnswerSel ).remove()
			} )

			if( 'radio' === jQuery( this )[0].type )
				this.checked = false // uncheck
		} )

		$the.hide().parent( 'li' ).append( $input )
		$input.hide().fadeIn( 300 ).focus() // animation

		// add a button to remove the user-entered text
		if( isMultiple ){

			const $ua = $demScreen.find( State.userAnswerSel )

			jQuery( '<span class="dem-add-answer-close">×</span>' )
				.insertBefore( $ua )
				.css( 'line-height', $ua.outerHeight() + 'px' )
				.on( 'click', function(){
					const $par = jQuery( this ).parent( 'li' )
					$par.find( 'input' ).remove()
					$par.find( 'a' ).fadeIn( 300 )
					jQuery( this ).remove()
				} )
		}

		return false // !!!
	}

	// Collect answers and return as a string
	jQuery.fn.demCollectAnsw = function(){
		const $form = this.closest( 'form' )
		const $answers = $form.find( '[type=checkbox],[type=radio]' )
		const userText = $form.find( State.userAnswerSel ).val()
		let answ = []
		const $checkbox = $answers.filter( '[type=checkbox]:checked' )

		// multiple
		if( $checkbox.length > 0 ){
			$checkbox.each( function(){
				answ.push( jQuery( this ).val() )
			} )
		}
		// single
		else{
			const str = $answers.filter( '[type=radio]:checked' )
			if( str.length )
				answ.push( str.val() )
		}

		// user_added
		if( userText ){
			answ.push( userText )
		}

		answ = answ.join( '~' )

		return answ ? answ : ''
	}

	// handle requests on click
	jQuery.fn.demDoAction = function( action ){

		const $the = this.first()
		const $dem = $the.closest( State.mainSel )
		const data = {
			dem_pid: $dem.data( 'opts' ).pid,
			dem_act: action,
			action : 'dem_ajax'
		}

		if( typeof data.dem_pid === 'undefined' ){
			console.warn( 'Poll id is not defined!' )
			return false
		}

		// Collect answers
		if( 'vote' === action ){
			data.answer_ids = $the.demCollectAnsw()
			if( ! data.answer_ids ){
				Utils.demShake( $the[0] )
				return false
			}
		}

		// revote button confirmation
		if( 'delVoted' === action && ! confirm( $the.data( 'confirm-text' ) ) )
			return false

		// add visitor answer button
		if( 'newAnswer' === action ){
			$the.demAddAnswer()
			return false
		}

		// AJAX
		Loader.setLoader( $the[0] )
		jQuery.post( State.ajaxurl, data, function( respond ){
			Loader.unsetLoader( $the[0] )

			// rebind events
			$the.closest( State.screenSel ).html( respond ).demInitActions()

			// scroll to the top of the poll block
			setTimeout( function(){
				jQuery( 'html:first,body:first' ).animate( { scrollTop: $dem.offset().top - 70 }, 500 )
			}, 200 )
		} )

		return false
	}

}
