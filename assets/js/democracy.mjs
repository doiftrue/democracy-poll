import Cookies from 'js-cookie'
import Utils from './Utils.mjs'
import State from './State.mjs'

document.addEventListener( 'DOMContentLoaded', democracyInit )

function democracyInit(){
	State.$dems = jQuery( State.demmainsel )
	if( ! State.$dems.length ){
		return
	}

	State.$loader = document.querySelector( '.dem-loader' )

	const opts = State.$dems.first().data( 'opts' )
	State.ajaxurl = opts.ajax_url
	State.answMaxHeight = opts.answs_max_height
	State.animSpeed = parseInt( opts.anim_speed )
	State.lineAnimSpeed = parseInt( opts.line_anim_speed )

	queueMicrotask( init ) // wait for functions
	democracyCacheInit()

	function init(){
		// Core Democracy events for all blocks
		const $demScreens = State.$dems.find( State.demScreen ).filter( ':visible' )
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
		const $cache = jQuery( '.dem-cache-screens' )
		if( $cache.length > 0 ){
			$cache.demCacheInit()
		}
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
			const autoVote = !!$this.find( 'input[type=radio][data-dem-act=vote]' ).first().length
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

	// Loader
	jQuery.fn.demSetLoader = function(){
		const $the = this

		if( State.$loader ){
			const loaderClone = State.$loader.cloneNode( true )
			loaderClone.style.display = 'table'
			$the.closest( State.demScreen ).append( loaderClone )
		}
		else {
			State.loaderTm = setTimeout( () => Utils.loadingDots( $the[0] ), 50 )
		}

		return this
	}

	jQuery.fn.demUnsetLoader = function(){

		if( State.$loader )
			this.closest( State.demScreen ).find( '.dem-loader' ).remove()
		else
			clearTimeout( State.loaderTm )

		return this
	}

	// Add user answer (link)
	jQuery.fn.demAddAnswer = function(){

		const $the = this.first()
		const $demScreen = $the.closest( State.demScreen )
		const isMultiple = $demScreen.find( '[type=checkbox]' ).length > 0
		const $input = jQuery( '<input type="text" class="' + State.userAnswer.replace( /\./, '' ) + '" value="">' ) // input for adding an answer

		// show vote button
		$demScreen.find( '.dem-vote-button' ).show()

		// handle radio inputs: uncheck and attach click handler
		$demScreen.find( '[type=radio]' ).each( function(){

			jQuery( this ).on( 'click', function(){
				$the.fadeIn( 300 )
				jQuery( State.userAnswer ).remove()
			} )

			if( 'radio' === jQuery( this )[0].type )
				this.checked = false // uncheck
		} )

		$the.hide().parent( 'li' ).append( $input )
		$input.hide().fadeIn( 300 ).focus() // animation

		// add a button to remove the user-entered text
		if( isMultiple ){

			const $ua = $demScreen.find( State.userAnswer )

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
		const userText = $form.find( State.userAnswer ).val()
		let answ = []
		const $checkbox = $answers.filter( '[type=checkbox]:checked' )

		// multiple
		if( $checkbox.length > 0 ){
			$checkbox.each( function(){
				answ.push( jQuery( this ).val() )
			} )
		}
		// single
		else {
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
		const $dem = $the.closest( State.demmainsel )
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
		if( 'delVoted' === action && !confirm( $the.data( 'confirm-text' ) ) )
			return false

		// add visitor answer button
		if( 'newAnswer' === action ){
			$the.demAddAnswer()
			return false
		}

		// AJAX
		$the.demSetLoader()
		jQuery.post( State.ajaxurl, data, function( respond ){
			$the.demUnsetLoader()

			// rebind events
			$the.closest( State.demScreen ).html( respond ).demInitActions()

			// scroll to the top of the poll block
			setTimeout( function(){
				jQuery( 'html:first,body:first' ).animate( { scrollTop: $dem.offset().top - 70 }, 500 )
			}, 200 )
		} )

		return false
	}

}

function democracyCacheInit(){
	// show notice
	jQuery.fn.demCacheShowNotice = function( type ){

		const $the = this.first()
		let $notice = $the.find( '.dem-youarevote' ).first() // "already voted"

		// If only logged-in users can vote
		if( type === 'blocked_because_not_logged_note' ){
			$the.find( '.dem-revote-button' ).remove() // remove revote button
			$notice = $the.find( '.dem-only-users' ).first()
		}

		$the.prepend( $notice.show() )
		// hide
		setTimeout( () => $notice.slideUp( 'slow' ), 10000 )

		return this
	}

	// set user's answers in results/vote block
	function cacheSetAnswrs( $screen, answrs ){
		const aids = answrs.split( /,/ )

		// results view
		if( $screen.hasClass( 'voted' ) ){
			const $dema = $screen.find( '.dem-answers' )
			const votedClass = $dema.data( 'voted-class' )
			const votedtxt = $dema.data( 'voted-txt' )

			jQuery.each( aids, function( key, val ){
				$screen.find( '[data-aid="' + val + '"]' )
					.addClass( votedClass )
					.attr( 'title', function(){
						return votedtxt + jQuery( this ).attr( 'title' )
					} )
			} )

			// remove "Vote" button
			$screen.find( '.dem-vote-link' ).remove()
		}
		// voting view
		else {
			const $answs = $screen.find( '[data-aid]' )
			const $btnVoted = $screen.find( '.dem-voted-button' )

			// set answers
			jQuery.each( aids, function( key, val ){
				$answs.filter( '[data-aid="' + val + '"]' ).find( 'input' ).prop( 'checked', 'checked' )
			} )

			// disable all
			$answs.find( 'input' ).prop( 'disabled', 'disabled' )

			// remove voting button
			$screen.find( '.dem-vote-button' ).remove()
			//$screen.find('[data-dem-act="vote"]').remove();

			// if "already voted" button exists, revote is disabled
			if( $btnVoted.length ){
				$btnVoted.show()
			}
			// show revote button
			else {
				$screen.find( 'input[value="vote"]' ).remove() // allow revote
				$screen.find( '.dem-revote-button-wrap' ).show()
			}
		}
	}

	jQuery.fn.demCacheInit = function(){
		return this.each( function(){
			const $the = jQuery( this )

			// find the main block
			let $dem = $the.prevAll( State.demmainsel + ':first' )
			if( ! $dem.length )
				$dem = $the.closest( State.demmainsel )

			if( ! $dem.length ){
				console.warn( 'Democracy: Main dem div not found' )
				return
			}

			const $screen = $dem.find( State.demScreen ).first() // main results block
			const dem_id = $dem.data( 'opts' ).pid
			const answrs = Cookies.get( 'demPoll_' + dem_id )
			const notVoteFlag = answrs === 'notVote' // If we already checked that user hasn't voted, don't request again
			const isAnswrs = !(typeof answrs == 'undefined') && !notVoteFlag

			// choose which screen to show and how to handle it
			const voteHTML = $the.find( State.demScreen + '-cache.vote' ).html()
			const votedHTML = $the.find( State.demScreen + '-cache.voted' ).html()

			// if poll is closed, only results should be cached. Exit.
			if( ! voteHTML ){
				return
			}

			// apply cached view
			// if results view is available
			const setVoted = isAnswrs && votedHTML
			$screen.html( (setVoted ? votedHTML : voteHTML) + '<!--cache-->' )
				.removeClass( 'vote voted' )
				.addClass( setVoted ? 'voted' : 'vote' )

			if( isAnswrs )
				cacheSetAnswrs( $screen, answrs )

			$screen.demInitActions( 1 )

			if( notVoteFlag ){
				return; // exit if it has already been checked that the user has not voted.
			}

			// If there are no votes in cookies and the plugin option keep_logs is enabled,
			// send a request to the database for checking, by event (mouse over a block).
			if( ! isAnswrs && $the.data( 'opt_logs' ) == 1 ){
				let tmout
				const notcheck__fn = function(){
					clearTimeout( tmout )
				}
				const check__fn = function(){
					tmout = setTimeout( function(){
						// Run once!
						if( $dem.hasClass( 'checkAnswDone' ) )
							return

						$dem.addClass( 'checkAnswDone' )

						const $forDotsLoader = $dem.find( '.dem-link' ).first()
						$forDotsLoader.demSetLoader()

						jQuery.post( State.ajaxurl,
							{
								dem_pid: $dem.data( 'opts' ).pid,
								dem_act: 'getVotedIds',
								action : 'dem_ajax'
							},
							function( reply ){
								$forDotsLoader.demUnsetLoader()
								// exit if there are no answers
								if( ! reply ){
									return;
								}

								$screen.html( votedHTML )
								cacheSetAnswrs( $screen, reply )

								$screen.demInitActions()

								// a message that you have voted or for users only
								$screen.demCacheShowNotice( reply )
							}
						)
					}, 700 )
					// 700 for optimization, so that the request is not sent instantly if you just swipe the mouse on the survey...
				}

				// hover
				$dem.on( 'mouseenter', check__fn ).on( 'mouseleave', notcheck__fn )
				$dem.on( 'click', check__fn )
			}

		} )
	}
}
