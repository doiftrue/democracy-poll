import Cookies from 'js-cookie'
import Utils from './Utils.mjs'
import State from './State.mjs'

// wait for jQuery
document.addEventListener( 'DOMContentLoaded', democracyInit )

function democracyInit(){
	State.$dems = jQuery( State.demmainsel )
	if( ! State.$dems.length ){
		return
	}

	let $demLoader = jQuery( '.dem-loader:first' )
	let Dem = {}

	Dem.opts = State.$dems.first().data( 'opts' )
	Dem.ajaxurl = Dem.opts.ajax_url
	State.answMaxHeight = Dem.opts.answs_max_height
	State.animSpeed = parseInt( Dem.opts.anim_speed )
	Dem.lineAnimSpeed = parseInt( Dem.opts.line_anim_speed )

	// INIT (ждем функции) ---
	setTimeout( function(){

		// Основные события Democracy для всех блоков
		let $demScreens = State.$dems.find( State.demScreen ).filter( ':visible' )
		let demScreensSetHeight = function(){
			$demScreens.each( function(){
				Utils.setHeight( jQuery( this ), 1 )
			} )
		}

		$demScreens.demInitActions( 1 )

		jQuery( window ).on( 'resize.demsetheight', demScreensSetHeight ) // высота при ресайзе

		jQuery( window ).on( 'load', demScreensSetHeight ) // высота еще раз

		Utils.maxAnswLimitInit() // ограничение выбора мульти ответов

		/*
		 * Обработка кэша.
		 * Нужен установленный js-cookie
		 * и дополнительные js переменные и методы самого Democracy.
		 */
		var $cache = jQuery( '.dem-cache-screens' )
		if( $cache.length > 0 ){
			//console.log('Democracy cache gear ON');
			$cache.demCacheInit()
		}

	}, 1 )


	// Инициализация всех событий связаных с внутренней частью каждого опроса: клики, высота, скрытие кнопки
	// применяется на '.dem-screen'
	jQuery.fn.demInitActions = function( noanimation ){

		return this.each( function(){
			// Устанавливает события клика для всех помеченных элементов в переданом элементе:
			// тут и AJAX запрос по клику и другие интерактивные события Democracy ----------
			var $this = jQuery( this )
			var attr = 'data-dem-act'

			$this.find( '[' + attr + ']' ).each( function(){
				var $the = jQuery( this )
				$the.attr( 'href', '' ) // удалим УРЛ чтобы не было видно УРЛ запроса

				$the.on( 'click', function( e ){
					e.preventDefault()
					$the.blur().demDoAction( $the.attr( attr ) )
				} )
			} )

			// Прячем кнопку сабмита, где нужно ------------
			var autoVote = !!$this.find( 'input[type=radio][data-dem-act=vote]' ).first().length
			if( autoVote ) $this.find( '.dem-vote-button' ).hide()

			// прячем внутряк если слишком много вариантов ответа
			Utils.setAnswsMaxHeight( $this )

			// анимация заполненных граф - line_animatin
			if( Dem.lineAnimSpeed ){
				$this.find( '.dem-fill' ).each( function(){
					var $fill = jQuery( this )
					//setTimeout(function(){ fill.style.width = was; }, State.animSpeed + 500); // на базе CSS transition - при сбросе тоже срабатывает и мешает...
					setTimeout( function(){
						$fill.animate( { width: $fill.data( 'width' ) }, Dem.lineAnimSpeed )
					}, State.animSpeed, 'linear' )
				} )
			}

			// Устанавливает высоту жестко ------------
			// Вешаем все на ресайз окна. Мобильники переворачиваются...
			Utils.setHeight( $this, noanimation )

			// событие сабмина формы
			$this.find( 'form' ).on( 'submit', function( e ){
				e.preventDefault()

				var act = jQuery( this ).find( 'input[name="dem_act"]' ).val()
				if( act )
					jQuery( this ).demDoAction( jQuery( this ).find( 'input[name="dem_act"]' ).val() )
			} )
		} )
	}

	// Loader
	jQuery.fn.demSetLoader = function(){
		const $the = this

		if( $demLoader.length ){
			$the.closest( State.demScreen ).append( $demLoader.clone().css( 'display', 'table' ) )
		}
		else {
			State.loader = setTimeout( () => Utils.demLoadingDots( $the[0] ), 50 )
		}

		return this
	}

	jQuery.fn.demUnsetLoader = function(){

		if( $demLoader.length )
			this.closest( State.demScreen ).find( '.dem-loader' ).remove()
		else
			clearTimeout( State.loader )

		return this
	}

	// Добавить ответ пользователя (ссылка)
	jQuery.fn.demAddAnswer = function(){

		var $the = this.first()
		var $demScreen = $the.closest( State.demScreen )
		var isMultiple = $demScreen.find( '[type=checkbox]' ).length > 0
		var $input = jQuery( '<input type="text" class="' + State.userAnswer.replace( /\./, '' ) + '" value="">' ) // поле добавления ответа

		// покажем кнопку голосования
		$demScreen.find( '.dem-vote-button' ).show()

		// обрабатывает input radio деселектим и вешаем событие клика
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

		// добавим кнопку удаления пользовательского текста
		if( isMultiple ){

			var $ua = $demScreen.find( State.userAnswer )

			jQuery( '<span class="dem-add-answer-close">×</span>' )
				.insertBefore( $ua )
				.css( 'line-height', $ua.outerHeight() + 'px' )
				.on( 'click', function(){
					var $par = jQuery( this ).parent( 'li' )
					$par.find( 'input' ).remove()
					$par.find( 'a' ).fadeIn( 300 )
					jQuery( this ).remove()
				} )
		}

		return false // !!!
	}

	// Собирает ответы и возращает их в виде строки
	jQuery.fn.demCollectAnsw = function(){
		var $form = this.closest( 'form' )
		var $answers = $form.find( '[type=checkbox],[type=radio],[type=text]' )
		var userText = $form.find( State.userAnswer ).val()
		var answ = []
		var $checkbox = $answers.filter( '[type=checkbox]:checked' )

		// multiple
		if( $checkbox.length > 0 ){
			$checkbox.each( function(){
				answ.push( jQuery( this ).val() )
			} )
		}
		// single
		else {
			var str = $answers.filter( '[type=radio]:checked' )
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

	// обрабатывает запросы при клике, вешается на событие клика
	jQuery.fn.demDoAction = function( action ){

		var $the = this.first()
		var $dem = $the.closest( State.demmainsel )
		var data = {
			dem_pid: $dem.data( 'opts' ).pid,
			dem_act: action,
			action : 'dem_ajax'
		}

		if( typeof data.dem_pid === 'undefined' ){
			console.log( 'Poll id is not defined!' )
			return false
		}

		// Соберем ответы
		if( 'vote' === action ){
			data.answer_ids = $the.demCollectAnsw()
			if( ! data.answer_ids ){
				Utils.demShake( $the[0] )
				return false
			}
		}

		// кнопка переголосовать, подтверждение
		if( 'delVoted' === action && !confirm( $the.data( 'confirm-text' ) ) )
			return false

		// кнопка добавления ответа посетителя
		if( 'newAnswer' === action ){
			$the.demAddAnswer()
			return false
		}

		// AJAX
		$the.demSetLoader()
		jQuery.post( Dem.ajaxurl, data, function( respond ){
			$the.demUnsetLoader()

			// устанавливаем все события
			$the.closest( State.demScreen ).html( respond ).demInitActions()

			// прокрутим к началу блока опроса
			setTimeout( function(){
				jQuery( 'html:first,body:first' ).animate( { scrollTop: $dem.offset().top - 70 }, 500 )
			}, 200 )
		} )

		return false
	}


	// КЭШ ---

	// показывает заметку
	jQuery.fn.demCacheShowNotice = function( type ){

		var $the = this.first()
		var $notice = $the.find( '.dem-youarevote' ).first() // "уже голосовал"

		// Если могут овтечать только зарегистрированные
		if( type === 'blocked_because_not_logged_note' ){
			$the.find( '.dem-revote-button' ).remove() // удаляем переголосовать
			$notice = $the.find( '.dem-only-users' ).first()
		}

		$the.prepend( $notice.show() )
		// hide
		setTimeout( function(){
			$notice.slideUp( 'slow' )
		}, 10000 )

		return this
	}

	// устанавливает ответы пользователя в блоке результатов/голосования
	Dem.cacheSetAnswrs = function( $screen, answrs ){
		var aids = answrs.split( /,/ )

		// если результаты
		if( $screen.hasClass( 'voted' ) ){
			var $dema = $screen.find( '.dem-answers' ),
				votedClass = $dema.data( 'voted-class' ),
				votedtxt = $dema.data( 'voted-txt' )

			jQuery.each( aids, function( key, val ){
				$screen.find( '[data-aid="' + val + '"]' )
					.addClass( votedClass )
					.attr( 'title', function(){
						return votedtxt + jQuery( this ).attr( 'title' )
					} )
			} )

			// уберем кнопку "Голосовать"
			$screen.find( '.dem-vote-link' ).remove()
		}
		// если голосование
		else {
			var $answs = $screen.find( '[data-aid]' ),
				$btnVoted = $screen.find( '.dem-voted-button' )

			// устанавливаем ответы
			jQuery.each( aids, function( key, val ){
				$answs.filter( '[data-aid="' + val + '"]' ).find( 'input' ).prop( 'checked', 'checked' )
			} )

			// все деактивирем
			$answs.find( 'input' ).prop( 'disabled', 'disabled' )

			// уберем голосовать
			$screen.find( '.dem-vote-button' ).remove()
			//$screen.find('[data-dem-act="vote"]').remove();

			// если есть кнопка "уже логосовали", то переголосование запрещено
			if( $btnVoted.length ){
				$btnVoted.show()
			}
			// показываем кнопку переголосовать
			else {
				$screen.find( 'input[value="vote"]' ).remove() // чтобы можно было переголосовать
				$screen.find( '.dem-revote-button-wrap' ).show()
			}
		}
	}

	jQuery.fn.demCacheInit = function(){
		return this.each( function(){
			var $the = jQuery( this )

			// ищем главный блок
			var $dem = $the.prevAll( State.demmainsel + ':first' )
			if( ! $dem.length )
				$dem = $the.closest( State.demmainsel )

			if( ! $dem.length ){
				console.warn( 'Democracy: Main dem div not found' )
				return
			}

			var $screen = $dem.find( State.demScreen ).first() // основной блок результатов
			var dem_id = $dem.data( 'opts' ).pid
			var answrs = Cookies.get( 'demPoll_' + dem_id )
			var notVoteFlag = answrs === 'notVote' // Если уже проверялось, что пользователь не голосовал, не отправляем запрос еще раз
			var isAnswrs = !(typeof answrs == 'undefined') && !notVoteFlag

			// обрабатываем экраны, какой показать и что делать при этом
			var voteHTML = $the.find( State.demScreen + '-cache.vote' ).html()
			var votedHTML = $the.find( State.demScreen + '-cache.voted' ).html()

			// если опрос закрыт должны кэшироваться только результаты голосования. Просто выходим.
			if( ! voteHTML ){
				return
			}

			// устанавливаем нужный кэш
			// если закрыт просмотрт ответов
			var setVoted = isAnswrs && votedHTML
			$screen.html( (setVoted ? votedHTML : voteHTML) + '<!--cache-->' )
				.removeClass( 'vote voted' )
				.addClass( setVoted ? 'voted' : 'vote' )

			if( isAnswrs )
				Dem.cacheSetAnswrs( $screen, answrs )

			$screen.demInitActions( 1 )

			if( notVoteFlag ){
				return; // exit if it has already been checked that the user has not voted.
			}

			// If there are no votes in cookies and the plugin option keep_logs is enabled,
			// send a request to the database for checking, by event (mouse over a block).
			if( ! isAnswrs && $the.data( 'opt_logs' ) == 1 ){
				var tmout
				var notcheck__fn = function(){
					clearTimeout( tmout )
				}
				var check__fn = function(){
					tmout = setTimeout( function(){
						// Выполняем один раз!
						if( $dem.hasClass( 'checkAnswDone' ) )
							return

						$dem.addClass( 'checkAnswDone' )

						var $forDotsLoader = $dem.find( '.dem-link' ).first()
						$forDotsLoader.demSetLoader()

						jQuery.post( Dem.ajaxurl,
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
								Dem.cacheSetAnswrs( $screen, reply )

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
