import State from './State.mjs'

export default class Utils {

	// Определяет высоту указанного элемента при свойстве - height:auto
	static detectRealHeight( $el ){

		// получим нужную высоту
		var $_el = $el.clone().css( { height: 'auto' } ).insertBefore( $el ) // insertAfter не подходит - глюк какой-то
		var realHeight = ($_el.css( 'box-sizing' ) === 'border-box') ? parseInt( $_el.css( 'height' ) ) : $_el.height()

		$_el.remove()

		//console.log($_el.css('height'), $_el.height(), $_el[0]);
		//setTimeout(function(){ console.log($_el.css('height'), $_el.height(), $_el[0]); }, 0);

		return realHeight
	}

	// Устанавливает высоту жестко
	static setHeight( $that, noanimation ){

		var newH = Utils.detectRealHeight( $that )

		// Анимируем до нужной выстоты
		if( !noanimation ){
			$that.css( { opacity: 0 } )
				.animate( { height: newH }, State.animSpeed, function(){
					jQuery( this ).animate( { opacity: 1 }, State.animSpeed * 1.5 )
				} )
		}
		else
			$that.css( { height: newH } )
	}

	// ограничение по высоте
	static setAnswsMaxHeight( $that ){

		if( State.answMaxHeight === '-1' || State.answMaxHeight === '0' || !State.answMaxHeight )
			return

		var $el = $that.find( '.dem-vote, .dem-answers' ).first()
		var maxHeight = parseInt( State.answMaxHeight )

		$el.css( { 'max-height': 'none', 'overflow-y': 'visible' } ) // сбросим если установлено

		var elHeight = ($el.css( 'box-sizing' ) === 'border-box') ? parseInt( $el.css( 'height' ) ) : $el.height()

		// сворачиваем, если больше чем максимальная высота и разница больше 100px - 100px прятать не резон...
		var diff = elHeight - maxHeight
		if( diff > 100 ){
			$el.css( 'position', 'relative' )

			var $overlay = jQuery( '<span class="dem__collapser"><span class="arr"></span></span>' ).appendTo( $el )
			var fn__expand = function(){
				$overlay.addClass( 'expanded' ).removeClass( 'collapsed' )
			}
			var fn__collaps = function(){
				$overlay.addClass( 'collapsed' ).removeClass( 'expanded' )
			}
			var timeout

			// не сворачиваем, если было развернуто
			if( $that.data( 'expanded' ) ){
				fn__expand()
			}
			else {
				fn__collaps()
				$el.height( maxHeight ).css( 'overflow-y', 'hidden' )
			}

			// клик на hover, чтобы не нужно было кликать для разворачивания
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
					$that.height( 'auto' ) // чтобы контейнер плавно передвигался вместе с внутяком, в конеце вернем ему высоту
					$el.stop().css( 'overflow-y', 'hidden' ).animate( { height: maxHeight }, State.animSpeed, function(){
						Utils.setHeight( $that, true )
					} )
				}
				// expand
				else {
					fn__expand()

					// определим высоту без скрытия
					var newH = Utils.detectRealHeight( $el )
					newH += 7 // запас для "добавить свой ответ"

					$that.data( 'expanded', true )
					$that.height( 'auto' ) // чтобы контейнер плавно передвигался вместе с внутяком, в конеце вернем ему высоту
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
			var maxAnsws = jQuery( this ).closest( State.demmainsel ).data( 'opts' ).max_answs
			var $checkboxs = jQuery( this ).closest( State.demScreen ).find( 'input[type="checkbox"]' )
			var $checked = $checkboxs.filter( ':checked' ).length

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
		let isInput = (el.tagName.toLowerCase() === 'input')
		let str = isInput ? el.value : el.innerHTML

		if( str.slice( -3 ) === '...' ){
			el[isInput ? 'value' : 'innerHTML'] = str.slice( 0, -3 )
		}
		else{
			el[isInput ? 'value' : 'innerHTML'] += '.'
		}

		State.loader = setTimeout( () => Utils.demLoadingDots( el ), 200 )
	}

}
