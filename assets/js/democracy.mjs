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

	State.$loader = document.querySelector( '.dem_loader_js' )

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
	// apply to '.dem-screen'
	function initScreen( screen ){
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
		if( State.lineAnimSpeed ){
			screen.querySelectorAll( '.dem-fill' ).forEach( fill => {
				setTimeout( () => animateFill( fill ), State.animSpeed )
			} )
		}

		// Set height explicitly ------------
		// Bind to window resize (mobile rotation, etc.)
		Utils.setHeight( screen, false )
	}

	function hasAutoVoteAnswers( screen ){
		return !! screen.querySelector( '.dem-vote-wrap[data-is_auto_vote="1"]' )
	}

	function hideAutoVoteButton( screen ){
		if( hasAutoVoteAnswers( screen ) && ! screen.querySelector( State.userAnswerSel ) ){
			screen.querySelectorAll( '.dem-vote-button' ).forEach( button => button.style.display = 'none' )
		}
	}

	// Add user answer (link)
	function addYourAnswerClickHandler( linkBtn ){
		const screen = linkBtn.closest( State.screenSel )
		const isMultiple = screen.querySelector( '[type=checkbox]' )
		if( isMultiple && Utils.maxAnswLimitData( screen ).isMaxReached ){
			Utils.demShake( linkBtn )

			return false
		}

		const newAInput = Utils.newEl( '<input type="text" class="dem-add-answer-txt" value="">' )
		newAInput.addEventListener( 'keydown', ev => {
			if( ev.key === 'Enter' && ! ev.isComposing ){
				ev.preventDefault()
				// we need to try to select button because on "dots loader" - if pass input dots will be added to answer text
				const actEl = screen.querySelector( '.dem-vote-button [data-dem-act="vote"]' ) || newAInput
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
		const btn = screen.querySelector( '.dem-vote-button' )
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
		const close = Utils.newEl( '<span class="dem-add-answer-close">×</span>' )
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

		newAInput.parentElement.querySelector('.dem-add-answer-close')?.remove()
		newAInput.remove()
		Utils.fadeIn( linkBtn )
		Utils.updateMaxAnswLimit( screen )
		hideAutoVoteButton( screen )
		requestAnimationFrame( () => Utils.setHeight( screen, true ) )
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
	function doAction( clickedEl, action ){
		const poll = clickedEl.closest( State.mainSel )
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
		const screen = clickedEl.closest( State.screenSel )
		if( ! screen ){
			return false
		}

		Loader.setLoader( clickedEl )
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
					initScreen( screen ) // rebind events
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
			{ duration: State.lineAnimSpeed, easing: 'linear', fill: 'forwards' }
		)
		.onfinish = () => fill.style.width = targetWidth
	}

	function isElemVisibleInViewport( el ) {
		const rect = el.getBoundingClientRect();
		return rect.bottom > 200 && rect.top < window.innerHeight; // bottom visible > 200px
	}

}
