document.addEventListener('DOMContentLoaded', function(){
	// EDIT POLL -----------------------
	var $answers_wrap = jQuery( '.new-poll-answers' );
	if( $answers_wrap.length ){
		var focusFunction = function(){
			// проверка нужно ли добавлять поле новое
			let $li = jQuery( this ).closest( 'li' )
			let $nextAnsw = $li.next( 'li.answ' )
			let $nextAnswTxt = $nextAnsw.find( '.answ-text' )

			if( $nextAnsw.length ) return this;

			// добавляем поле
			jQuery( this ).addAnswField();
		};

		//var blurFunction = function(){};

		// добавляет li блок (поле нового ответа) после текущего li
		jQuery.fn.addAnswField = function(){
			var $li = this.closest( 'li' )
			var $_li = $li.clone().addClass( 'new' )

			$_li.find( 'input' ).remove();

			var $input = jQuery( '<input class="answ-text" type="text" name="dmc_new_answers[]">' );
			$input.on( 'focus', focusFunction );
			// удаляем блок, если в поле не было введено данных
			$input.on( 'blur', function(){
				if( ! $input.val() ) $_li.remove();
			} );

			$_li.prepend( $input );
			$li.after( $_li );
			return this;
		};

		// поле с новым ответом
		$answers_wrap.find( '.answ-text' ).focus( focusFunction );
		$answers_wrap.find( 'li.answ' ).last().addAnswField();


		// кнопки удаления
		$answers_wrap.find( 'li.answ' ).each( function(){
			jQuery( this ).append( '<span class="dem-del-button">×</span>' );
		} );
		// событие удаления
		$answers_wrap.on( 'click', '.dem-del-button', function(){
			jQuery( this ).parent( 'li' ).remove();

			// Перестроим порядок, если он вообще установлен
			if( $answers_wrap.find( 'li.answ:first input[name $= "[aorder]"]' ).val() > 0 )
				window.updateAnswersOrder();
		} );

		// datepicker
		jQuery( 'input[name="dmc_end"], input[name="dmc_added"]' ).datepicker( { dateFormat: 'dd-mm-yy' } );

		// множественный ответ и user_voted
		var $multiple = jQuery( 'input[name="dmc_multiple"]' ),
			$multiNum = $multiple.parent().find( '[type="number"]' ),
			$users_voted = $answers_wrap.find( 'input[name="dmc_users_voted"]' );

		// ReSet value of 'dmc_users_voted' when vote count change
		$answers_wrap.on( 'change.reset_users_voted', 'input[name$="[votes]"]', function(){
			if( ! $multiple.is( ':checked' ) ){
				var sum = 0;
				$answers_wrap.find( 'input[name$="[votes]"]' ).each( function(){
					sum += Number( jQuery( this ).val() );
				} );

				$users_voted.val( sum );
			}
		} )

		$multiple.change( function(){
			$multiple.is( ':checked' ) ? $multiNum.show().focus() : $multiNum.hide();
			$multiple.is( ':checked' ) ? $users_voted.removeProp( 'readonly' ) : $users_voted.prop( 'readonly', 1 );

			$answers_wrap.find( 'input[name$="[votes]"]' ).first().trigger( 'change.reset_users_voted' ); // to reset dmc_users_voted
		} )
		$multiNum.change( function(){
			$multiple.val( $multiNum.val() );
		} )

		// sortable - set answer order
		if( 1 ){
			var $orderEls = $answers_wrap.find( '> .answ:not(.new)' );

			// для глобального доступа
			window.updateAnswersOrder = function(){
				$answers_wrap.find( '> .answ:not(.new)' ).each( function( nn ){
					jQuery( this ).find( 'input[name $= "[aorder]"]' ).val( nn + 1 );
				} );

				$answers_wrap.find( '.reset__aorder' ).slideDown();
				jQuery( '.answers__order' ).slideUp();
			};

			// add order handle
			$orderEls.css( { position: 'relative' } ).prepend( '<span class="sorthand dashicons dashicons-menu" style="position:absolute; left:-2.5em; margin-left:0; margin-top:.19em; cursor:move;"></span>' );

			$answers_wrap.sortable( {
				axis  : 'y',
				handle: '.sorthand',
				items : '> .answ:not(.new)',
				update: window.updateAnswersOrder
			} );

			$answers_wrap.find( '.reset__aorder' ).on( 'click', function(){
				var $elsVotes = $answers_wrap.find( '> .answ:not(.new)' ),
					$elsVotesNo = $answers_wrap.find( '> .answ.new, > .not__answer' );

				// сбросим значения
				$elsVotes.find( 'input[name $= "[aorder]"]' ).val( '0' );

				// отсортируем элементы
				$elsVotes.sort( function( a, b ){
					return parseInt( jQuery( b ).find( 'input[name $= "[votes]"]' ).val() ) - parseInt( jQuery( a ).find( 'input[name $= "[votes]"]' ).val() );
				} ).appendTo( $answers_wrap );

				// и в конец добавим несортируемые
				$elsVotesNo.appendTo( $answers_wrap );

				// спрячем кнопку
				jQuery( this ).slideUp();
				jQuery( '.answers__order' ).slideDown();
			} );

		}

	}


	// DESIGN ---------------------------------------
	if( jQuery( '.dempage_design' ).length ){
		jQuery( '.dem-screen' ).height( function(){
			return jQuery( this ).outerHeight();
		} );

		jQuery( '[data-dem-act], .democracy a' ).on( 'click', function( e ){
			e.preventDefault();
		} ); // отменяем клики

		// предпросмотр
		var $demLoader = jQuery( document ).find( '.dem-loader' ).first(); // loader
		jQuery( '.poll.show-loader .dem-screen' ).append( $demLoader.css( 'display', 'table' ) );

		// wpColorPicker
		jQuery( '.iris_color' ).wpColorPicker();

		var myOptions = {},
			$preview = jQuery( '.polls-preview' );
		myOptions.change = function( event, ui ){
			var hexcolor = jQuery( this ).wpColorPicker( 'color' );
			$preview.css( 'background-color', hexcolor );
			//console.log( hexcolor );
		};
		jQuery( '.preview-bg' ).wpColorPicker( myOptions );

		// checkboks for buttons
		var selectable_els = jQuery( '.selectable_els' );
		selectable_els.each( function(){
			var $elswrap = jQuery( this );
			$elswrap.find( 'label' ).on( 'click', function(){
				$elswrap.find( 'input[type="radio"]:not(.demdummy)' ).removeProp( 'checked' );
				jQuery( this ).find( 'input[type="radio"]:not(.demdummy)' ).prop( 'checked', 'checked' );
				//console.log( jQuery(this).find('input[type="radio"]')[0] );
			} );
		} );
	}


	// POLLS LIST
	// height toggle
	var $answs = jQuery( '.compact-answ' ),
		$icon = jQuery( '<span class="dashicons dashicons-exerpt-view"></span>' ).on( 'click', function(){
			jQuery( this ).toggleClass( 'active' );
			$answs.trigger( 'click' );
		} ),
		$table = jQuery( '.tablenav-pages' );

	$answs.css( { cursor: 'pointer' } ).on( 'click', function(){
		var dataHeight = jQuery( this ).data( 'height' ) || 'auto';
		jQuery( this ).data( 'height', jQuery( this ).height() ).height( dataHeight );
	} );

	// убедимяс что это та таблица
	if( $table.closest( '.wrap' ).find( 'table .column-id' ).length )
		$table.prepend( $icon );

} );

