import Utils from './Utils.mjs'
import State from './State.mjs'
import Loader from './Loader.mjs'
import Cache from './Cache.mjs'

document.addEventListener( 'DOMContentLoaded', democracyInit )

function democracyInit(){
	const polls = document.querySelectorAll( State.mainSel )
	if( ! polls.length ){
		return
	}

	State.$loader = document.querySelector( '.dem-loader' )

	const opts = Cache.getOpts( polls[0] )
	State.ajaxurl = opts.ajax_url
	State.answMaxHeight = opts.answs_max_height
	State.animSpeed = parseInt( opts.anim_speed )
	State.lineAnimSpeed = parseInt( opts.line_anim_speed )

	queueMicrotask( init ) // wait for functions

	// Core Democracy events for all blocks
	function init(){
		const demScreens = []
		polls.forEach( poll => {
			const screen = poll.querySelector( State.screenSel )
			if( screen && Utils.isVisible( screen ) ){
				demScreens.push( screen )
			}
		} )

		demScreens.forEach( screen => initActions( screen, false ) )

		const setScreenHeight = () => demScreens.forEach( screen => Utils.setHeight( screen ) )
		window.addEventListener( 'load', setScreenHeight ) // update height once more
		window.addEventListener( 'resize', setScreenHeight ) // update height

		Utils.maxAnswLimitInit()

		/*
		 * Cache handling.
		 * Requires js-cookie to be installed
		 * and extra Democracy variables/methods.
		 */
		Cache.actionsHandler = ( screen, doAnimation = true ) => initActions( screen, doAnimation )
		Cache.initAll()
	}

	// Initialize all events for each poll: clicks, height, button visibility
	// applies to '.dem-screen'
	function initActions( screen, doAnimation = true ){
		// Attach click handlers for all marked elements inside the given element:
		// includes AJAX on click and other Democracy interactions ----------
		const attr = 'data-dem-act'

		// Add Click events
		screen.querySelectorAll( '[' + attr + ']' ).forEach( act => {
			act.setAttribute( 'href', '' ) // clear URL so the request URL isn't visible
			act.addEventListener( 'click', ( ev ) => {
				ev.preventDefault()
				act.blur()
				doAction( act, act.getAttribute( attr ) )
			} )
		} )

		// Hide submit button
		if( screen.querySelector( 'input[type=radio][data-dem-act=vote]' ) ){
			screen.querySelectorAll( '.dem-vote-button' ).forEach( button => button.style.display = 'none' )
		}

		Utils.resetHeight( screen )

		// collapse content if there are too many answers
		Utils.setAnswsMaxHeight( screen )

		// animate filled bars - line_animation
		if( State.lineAnimSpeed ){
			screen.querySelectorAll( '.dem-fill' ).forEach( fill => {
				setTimeout( function(){
					jQuery( fill ).animate( { width: fill.dataset.width }, State.lineAnimSpeed )
				}, State.animSpeed, 'linear' )
			} )
		}

		// Set height explicitly ------------
		// Bind to window resize (mobile rotation, etc.)
		Utils.setHeight( screen, doAnimation )

		// form submit event
		screen.querySelectorAll( 'form' ).forEach( form => {
			form.addEventListener( 'submit', function( e ){
				e.preventDefault()

				const actInput = form.querySelector( 'input[name="dem_act"]' )
				if( actInput?.value ){
					doAction( form, actInput.value )
				}
			} )
		} )
	}

	// Add user answer (link)
	function addAnswer( the ){
		const $the = jQuery( the )
		const $demScreen = $the.closest( State.screenSel )
		const isMultiple = $demScreen.find( '[type=checkbox]' ).length > 0
		const $input = jQuery( '<input type="text" class="dem-add-answer-txt" value="">' ) // input for adding an answer

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
	function collectAnsw( the ){
		const form = the.closest( 'form' )
		const userTextInput = form.querySelector( State.userAnswerSel )
		const userText = userTextInput ? userTextInput.value : ''
		let answ = []

		// multiple
		const checkbox = form.querySelectorAll( '[type=checkbox]:checked' )
		if( checkbox.length ){
			checkbox.forEach( input => answ.push( input.value ) )
		}
		// single
		else{
			const radio = form.querySelector( '[type=radio]:checked' )
			radio && answ.push( radio.value )
		}

		// user_added
		if( userText ){
			answ.push( userText )
		}

		answ = answ.join( '~' )

		return answ || ''
	}

	// handle requests on click
	function doAction( the, action ){
		const poll = the.closest( State.mainSel )
		const data = {
			dem_pid: Cache.getOpts( poll ).pid,
			dem_act: action,
			action : 'dem_ajax'
		}

		if( typeof data.dem_pid === 'undefined' ){
			console.warn( 'Poll id is not defined!' )
			return false
		}

		// Collect answers
		if( 'vote' === action ){
			data.answer_ids = collectAnsw( the )
			if( ! data.answer_ids ){
				Utils.demShake( the )
				return false
			}
		}

		// revote button confirmation
		if( 'delVoted' === action && ! confirm( the.dataset['confirm_text'] ) ){
			return false
		}

		// add visitor answer button
		if( 'newAnswer' === action ){
			addAnswer( the )
			return false
		}

		// AJAX
		const screen = the.closest( State.screenSel )
		if( ! screen ){
			return false
		}

		Loader.setLoader( the )
		Cache.post( State.ajaxurl, data ).then( html => {
			Loader.unsetLoader( screen )

			if( ! html ){
				return
			}

			delete screen.dataset['expanded']

			screen.innerHTML = html
			initActions( screen ) // rebind events

			// scroll to the top of the poll block
			setTimeout( () => {
				window.scrollTo( {
					top     : poll.getBoundingClientRect().top + window.pageYOffset - 70,
					behavior: 'smooth'
				} )
			}, 200 )
		} )
			.catch( error => {
				Loader.unsetLoader( screen )
				console.warn( 'Democracy: AJAX request failed', error )
			} )

		return false
	}

}
