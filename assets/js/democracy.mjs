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

		demScreens.forEach( screen => initActions( screen ) )

		const setScreenHeight = () => demScreens.forEach( screen => Utils.setHeight( screen ) )
		window.addEventListener( 'load', setScreenHeight ) // update height once more
		window.addEventListener( 'resize', setScreenHeight ) // update height

		Utils.maxAnswLimitInit()

		/*
		 * Cache handling.
		 * Requires js-cookie to be installed
		 * and extra Democracy variables/methods.
		 */
		Cache.actionsHandler = initActions
		Cache.initAll()
	}

	// Initialize all events for each poll: clicks, height, button visibility
	// applies to '.dem-screen'
	function initActions( screen ){
		// Attach click handlers for all marked elements inside the given element:
		// includes AJAX on click and other Democracy interactions ----------
		const attr = 'data-dem-act'

		// Add Click events
		screen.querySelectorAll( '[' + attr + ']' ).forEach( act => {
			if( act.tagName === 'A' ){
				act.setAttribute( 'href', '' ) // clear URL so the request URL isn't visible
			}
			act.addEventListener( 'click', ( ev ) => {
				ev.preventDefault()
				act.blur()
				doAction( act, act.getAttribute( attr ) )
			} )
		} )

		// Hide vote button
		if( screen.querySelector( 'input[type=radio][data-dem-act=vote]' ) ){
			screen.querySelectorAll( '.dem-vote-button' ).forEach( button => button.style.display = 'none' )
		}

		Utils.resetHeight( screen )

		// collapse content if there are too many answers
		Utils.setAnswsMaxHeight( screen )

		// animate filled bars - line_animation
		if( State.lineAnimSpeed ){
			screen.querySelectorAll( '.dem-fill' ).forEach( fill => {
				setTimeout( () => animateFill( fill ), State.animSpeed )
			} )
		}

		// Set height explicitly ------------
		// Bind to window resize (mobile rotation, etc.)
		Utils.setHeight( screen, false )
	}

	function animateFill( fill ){
		const targetWidth = fill.dataset['width']
		if( ! targetWidth ){
			return
		}

		if( ! fill.animate ){
			fill.style.width = targetWidth
			return
		}

		fill.animate( [
				{ width: window.getComputedStyle( fill ).width },
				{ width: targetWidth }
			],
			{ duration: State.lineAnimSpeed, easing: 'linear', fill: 'forwards' }
		)
			.onfinish = () => fill.style.width = targetWidth
	}

	// Add user answer (link)
	function addAnswer( the ){
		const screen = the.closest( State.screenSel )
		const isMultiple = screen.querySelector( '[type=checkbox]' )
		const input = Utils.newEl( '<input type="text" class="dem-add-answer-txt" value="">' )

		// show vote button
		const btn = screen.querySelector( '.dem-vote-button' )
		btn && Utils.showElement( btn )

		// handle radio inputs: uncheck and attach click handler
		screen.querySelectorAll( '[type=radio]' ).forEach( radio => {
			radio.checked = false // uncheck
			radio.addEventListener( 'click', () => {
				Utils.fadeIn( the )
				document.querySelectorAll( State.userAnswerSel ).forEach( node => node.remove() )
			} )
		} )

		//
		Utils.hideElement( the )
		the.parentElement.append( input )
		Utils.hideElement( input )
		Utils.fadeIn( input )
		input.focus()

		// add a button to remove the user-entered text
		if( isMultiple ){
			const close = Utils.newEl( '<span class="dem-add-answer-close">×</span>' )
			close.style.lineHeight = input.offsetHeight + 'px'
			input.before( close )

			close.addEventListener( 'click', ev => {
				const parent = close.parentElement
				const link = parent.querySelector( 'a' )
				parent.querySelector( 'input' ).remove()
				close.remove()
				Utils.fadeIn( link )
			} )
		}

		return false // !!!
	}

	// Collect answers and return as a string
	function collectAnsw( the ){
		const screen = the.closest( State.screenSel )
		if( ! screen ){
			return ''
		}

		const userTextInput = screen.querySelector( State.userAnswerSel )
		const userText = userTextInput ? userTextInput.value : ''
		let answ = []

		// multiple
		const checkbox = screen.querySelectorAll( '[type=checkbox]:checked' )
		if( checkbox.length ){
			checkbox.forEach( input => answ.push( input.value ) )
		}
		// single
		else{
			const radio = screen.querySelector( '[type=radio]:checked' )
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
		const ajaxData = {
			dem_pid: Cache.getOpts( poll ).pid,
			dem_act: action,
			action : 'dem_ajax'
		}

		if( typeof ajaxData.dem_pid === 'undefined' ){
			console.warn( 'Poll id is not defined!' )
			return false
		}

		// Collect answers
		if( 'vote' === action ){
			ajaxData.answer_ids = collectAnsw( the )
			if( ! ajaxData.answer_ids ){
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
		Cache.post( State.ajaxurl, ajaxData )
			.finally( () => Loader.unsetLoader( screen ) )
			.then( html => {
				if( ! html ){
					return
				}

				screen.dataset['expanded'] = 'true'

				const fadeDuration = 500
				screen.style.transition = `opacity ${fadeDuration}ms ease`
				screen.style.opacity = 0
				setTimeout( () => {
					screen.innerHTML = html
					initActions( screen ) // rebind events
					isElemVisibleInViewport( poll ) || poll.scrollIntoView( { behavior: 'smooth', block: 'start' } )
					screen.style.opacity = 1
				}, fadeDuration )
			} )
			.catch( error => {
				Loader.unsetLoader( screen )
				console.warn( 'Democracy: AJAX request failed', error )
			} )

		return false
	}

	function isElemVisibleInViewport( el ) {
		const rect = el.getBoundingClientRect();
		return rect.bottom > 200 && rect.top < window.innerHeight; // bottom visible > 200px
	}

}
