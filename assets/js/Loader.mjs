import Utils from './Utils.mjs'
import State from './State.mjs'

export default class Loader {

	static setLoader( target ){
		if( State.$loader ){
			const loaderClone = State.$loader.cloneNode( true )
			loaderClone.style.display = 'table'

			const screen = target.closest( State.screenSel )
			if( screen ){
				screen.append( loaderClone )
			}
		}
		else{
			State.loaderTmr = setTimeout( () => Utils.loadingDots( target ), 50 )
		}
	}

	static unsetLoader( target ){
		if( State.$loader ){
			const screen = target.closest( State.screenSel )
			screen.querySelectorAll( '.dem-loader' ).forEach( node => node.remove() )
		}
		else{
			clearTimeout( State.loaderTmr )
		}
	}

}
