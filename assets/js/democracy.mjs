import Utils from './Utils.mjs'
import Loader from './Loader.mjs'
import Cache from './Cache.mjs'
import Config from './Config.mjs'
import PollState from './PollState.mjs'
import Notice from './Notice.mjs'

document.addEventListener( 'DOMContentLoaded', democracyInit )

function democracyInit(){
	const polls = document.querySelectorAll( Config.mainSel )
	if( ! polls.length ){
		return
	}

	Config.$loader = document.querySelector( '.dem_loader_js' )

	const config = window.democracyPollConfig || {}
	Config.ajaxurl = config.ajax_url
	Config.cookieDays = config.cookie_days
	Config.animSpeed = parseInt( config.anim_speed )
	Config.lineAnimSpeed = parseInt( config.line_anim_speed )

	queueMicrotask( init ) // wait for functions

	// Core Democracy events for all blocks
	function init(){
		const demScreens = []
		polls.forEach( poll => {
			PollState.get( poll )
			const screen = poll.querySelector( Config.screenSel )
			if( screen && Utils.isVisible( screen ) ){
				demScreens.push( screen )
			}
		} )

		demScreens.forEach( screen => initScreen( screen ) )

		const setScreenHeight = () => demScreens.forEach( screen => Utils.setHeight( screen ) )
		window.addEventListener( 'load', setScreenHeight ) // update height once more
		window.addEventListener( 'resize', setScreenHeight ) // update height

		Utils.maxAnswLimitInit()

		/*
		 * Cache handling.
		 * Requires js-cookie to be installed
		 * and extra Democracy variables/methods.
		 */
		Cache.actionsHandler = initScreen
		Cache.initAll()
	}

	// Initialize all events for each poll: clicks, height, button visibility
	// apply to '.dem_screen_js'
	function initScreen( screen ){
		// init all actions elements
		screen.querySelectorAll( '[data-dem-act]' )
			.forEach( actionEl => {
				// clear URL so the request URL isn't visible
				( actionEl.tagName === 'A' ) && actionEl.setAttribute( 'href', '' )

				actionEl.addEventListener( 'click', ( ev ) => {
					ev.preventDefault()
					actionEl.blur()
					doAction( actionEl, actionEl.getAttribute( 'data-dem-act' ) )
				} )
			} )

		hideAutoVoteButton( screen )

		Utils.resetHeight( screen )

		// collapse content if there are too many answers
		Utils.setAnswsMaxHeight( screen )

		Utils.updateMaxAnswLimit( screen )

		// animate filled bars - line_animation
		if( Config.lineAnimSpeed ){
			screen.querySelectorAll( '.dem_fill_js' ).forEach( fill => {
				setTimeout( () => animateFill( fill ), Config.animSpeed )
			} )
		}

		// Set height explicitly ------------
		// Bind to window resize (mobile rotation, etc.)
		Utils.setHeight( screen, false )
	}

	function hasAutoVoteAnswers( screen ){
		return !! screen.querySelector( '.dem_vote_wrap_js[data-is_auto_vote="1"]' )
	}

	function hideAutoVoteButton( screen ){
		if( hasAutoVoteAnswers( screen ) && ! screen.querySelector( Config.userAnswerSel ) ){
			screen.querySelectorAll( '.dem_vote_button_js' ).forEach( button => button.style.display = 'none' )
		}
	}

	// Add user answer (link)
	function addYourAnswerClickHandler( linkBtn ){
		const screen = linkBtn.closest( Config.screenSel )
		const isMultiple = screen.querySelector( '[type=checkbox]' )
		if( isMultiple && Utils.maxAnswLimitData( screen ).isMaxReached ){
			Utils.demShake( linkBtn )

			return false
		}

		const newAInput = Utils.newEl( '<input type="text" class="dem-add-answer-txt dem_add_answer_txt_js" value="">' )
		newAInput.addEventListener( 'keydown', ev => {
			if( ev.key === 'Enter' && ! ev.isComposing ){
				ev.preventDefault()
				// we need to try to select button because on "dots loader" - if pass input dots will be added to answer text
				const actEl = screen.querySelector( '.dem_vote_button_js [data-dem-act="vote"]' ) || newAInput
				doAction( actEl, 'vote' )
			}
		} )
		newAInput.closeListeners = []
		newAInput.closeNewAnswer = () => closeNewAnswer( screen, newAInput, linkBtn )

		// handle radio inputs: uncheck and attach click handler
		screen.querySelectorAll( '[type=radio]' ).forEach( radio => {
			radio.checked = false
			newAInput.closeListeners.push( radio )
			radio.addEventListener( 'click', newAInput.closeNewAnswer )
		} )

		// show vote button
		const btn = screen.querySelector( '.dem_vote_button_js' )
		btn && Utils.showElement( btn )

		// insert in DOM
		Utils.hideElement( linkBtn )
		linkBtn.after( newAInput )
		Utils.hideElement( newAInput )
		Utils.fadeIn( newAInput )
		newAInput.focus()
		Utils.updateMaxAnswLimit( screen )
		requestAnimationFrame( () => Utils.setHeight( screen, true ) )

		// add a button to remove the user-entered text
		const close = Utils.newEl( '<span class="dem-add-answer-close dem_add_answer_close_js">×</span>' )
		close.style.lineHeight = newAInput.offsetHeight + 'px' // !!! after `linkBtn.after( newAInput )`
		close.addEventListener( 'click', newAInput.closeNewAnswer )
		newAInput.before( close )
	}

	function closeNewAnswer( screen, newAInput, linkBtn ) {
		// no input in DOM - nothing to remove
		if( ! newAInput.isConnected ){
			return
		}

		newAInput.closeListeners.forEach( radio => radio.removeEventListener( 'click', newAInput.closeNewAnswer ) )

		newAInput.parentElement.querySelector( '.dem_add_answer_close_js' )?.remove()
		newAInput.remove()
		Utils.fadeIn( linkBtn )
		Utils.updateMaxAnswLimit( screen )
		hideAutoVoteButton( screen )
		requestAnimationFrame( () => Utils.setHeight( screen, true ) )
	}

	// Collect answers and return as a string
	function collectAnsw( the ){
		const screen = the.closest( Config.screenSel )
		if( ! screen ){
			return ''
		}

		const userTextInput = screen.querySelector( Config.userAnswerSel )
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
	function doAction( clickedEl, action ){
		const poll = clickedEl.closest( Config.mainSel )
		const ajaxData = {
			dem_pid: PollState.get( poll ).pid,
			dem_act: action,
			action : 'dem_ajax'
		}

		if( typeof ajaxData.dem_pid === 'undefined' ){
			console.warn( 'Poll id is not defined!' )
			return false
		}

		// Collect answers
		if( 'vote' === action ){
			ajaxData.answer_ids = collectAnsw( clickedEl )
			if( ! ajaxData.answer_ids ){
				Utils.demShake( clickedEl )
				return false
			}
		}

		// revote button confirmation
		if( 'delVoted' === action && ! confirm( clickedEl.dataset['confirm_text'] ) ){
			return false
		}

		// add visitor answer button
		if( 'newAnswer' === action ){
			addYourAnswerClickHandler( clickedEl )
			return false
		}

		// AJAX
		const screen = clickedEl.closest( Config.screenSel )
		if( ! screen ){
			return false
		}

		Loader.setLoader( clickedEl )
		Cache.post( Config.ajaxurl, ajaxData )
			.finally( () => Loader.unsetLoader( screen ) )
			.then( response => {
				if( ! response.screen_html ){
					Notice.set( poll, response.notice )
					return
				}

				screen.dataset['expanded'] = 'true'

				const fadeDuration = 500
				screen.style.transition = `opacity ${fadeDuration}ms ease`
				screen.style.opacity = 0
				setTimeout( () => {
					screen.innerHTML = response.screen_html
					initScreen( screen ) // rebind events
					Notice.set( poll, response.notice )
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
			{ duration: Config.lineAnimSpeed, easing: 'linear', fill: 'forwards' }
		)
		.onfinish = () => fill.style.width = targetWidth
	}

	function isElemVisibleInViewport( el ) {
		const rect = el.getBoundingClientRect();
		return rect.bottom > 200 && rect.top < window.innerHeight; // bottom visible > 200px
	}

}
