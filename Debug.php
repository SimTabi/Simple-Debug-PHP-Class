<?php
class Debug {
	static protected $reporting = E_ALL;
	static public    function reporting ( $reporting = null ) {
		if ( ! func_num_args() )
			return self::$reporting;
		self::$reporting = $reporting;
	}
	static public    function log () {
		self::$reporting && 
			print( 
				PHP_EOL . '<pre class="debug log">'
				. implode( 
					'</pre>' . PHP_EOL . '<pre class="log">' 
					, array_map( 'Debug::var_export', func_get_args() )
				)
				. '</pre>'
			);
	}
	static public    function dump () {
		self::$reporting && 
			die( call_user_func_array( 'Debug::log', func_get_args() ) );
	}
	static private   $time;
	static private   $chrono;
	static public    function chrono ( $print = null, $scope = '' ) {
		if ( ! self::$reporting )
			return;
		if ( ! isset( self::$time[ $scope ] ) )
			$chrono [] = '<b class="init">' . $scope . ' chrono init</b>';
		elseif ( is_string( $print ) ) {
			$chrono[] = sprintf('<span class="time">%s -> %s: %fs</span>'
				, $scope
				, $print
				, round( self::$chrono[ $scope ][ $print ] = microtime( true ) - self::$time[ $scope ], 6 )
			);
		} elseif ( $print && isset( self::$chrono[ $scope ] ) ) {
			asort( self::$chrono[ $scope ] );
			$base = reset ( self::$chrono[ $scope ] ); // shortest duration
			foreach( self::$chrono[ $scope ] as $event => $duration )
				$table[] = sprintf( '%5u - %-38.38s <i>%7fs</i>'
					, round( $duration / $base, 2 )
					, $event
					, round( $duration, 3 )
				);
			$chrono[] = '<div class="table"><b>' . $scope . ' chrono table</b>' . PHP_EOL .
				sprintf( '%\'-61s %-46s<i>duration</i>%1$s%1$\'-61s'
					, PHP_EOL
					, 'unit - action'
				) . 
				implode( PHP_EOL, $table ) . PHP_EOL . 
				'</div>';
		}
		echo self::style(), PHP_EOL, '<pre class="debug chrono">', implode( PHP_EOL, $chrono ), '</pre>';
		return self::$time[ $scope ] = microtime( true );
	}
	static private   $registered;
	static public    function register ( $init = true ) {
		if ( $init) {
			if ( ! self::$registered )
				self::$registered = array(
					'error_reporting'  => error_reporting()
					, 'display_errors' => ini_get( 'display_errors' )
					, 'shutdown'       => register_shutdown_function( 'Debug::shutdown' )
				);
			self::$registered[ 'shutdown' ] = true;
			error_reporting( E_ALL );
			set_error_handler( 'Debug::handler', E_ALL );
			set_exception_handler( 'Debug::exception' );
			ini_set( 'display_errors', 0 );
		} else if ( self::$registered ) {
			self::$registered[ 'shutdown' ] = false;
			error_reporting( self::$registered[ 'error_reporting' ] );
			restore_error_handler();
			restore_exception_handler();
			ini_set( 'display_errors', self::$registered[ 'display_errors' ] );
		}
	}
	static protected $error     = array(
		-1                    => 'Exception'
		, E_ERROR             => 'Fatal'
		, E_RECOVERABLE_ERROR => 'Recoverable'
		, E_WARNING           => 'Warning'
		//, E_PARSE             => 'Parse'
		, E_NOTICE            => 'Notice'
		, E_STRICT            => 'Strict'
		, E_DEPRECATED        => 'Deprecated'
		, E_CORE_ERROR        => 'Fatal'
		, E_CORE_WARNING      => 'Warning'
		//, E_COMPILE_ERROR     => 'Compile Fatal'
		//, E_COMPILE_WARNING   => 'Compile Warning'
		, E_USER_ERROR        => 'Fatal'
		, E_USER_WARNING      => 'Warning'
		, E_USER_NOTICE       => 'Notice'
		, E_USER_DEPRECATED   => 'Deprecated'
	);
	static public    function handler ( $type, $message, $file, $line, $scope, $stack = null ) {
		global $php_errormsg; // set global error message regardless track errors settings
		$php_errormsg = preg_replace( '~^.*</a>\]: +(?:\([^\)]+\): +)?~', null, $message ); // clean useless infos
		if ( ! self::$reporting ) // de-activate
			return false;
		$stack = $stack ?: array_slice( debug_backtrace( false ),  $type & E_USER_ERROR | E_USER_WARNING | E_USER_NOTICE ?  2 : 1 ); // clean stack depending if error is user triggered or not
		self::overload( $stack, $file, $line  ); // switch line & file if overloaded method triggered the error
		echo self::style(), PHP_EOL, '<pre class="debug error ',strtolower( self::$error[ $type ] ) ,'">', PHP_EOL, 
			sprintf( '<b>%s</b>: %s in <b>%s</b> on line <b>%s</b>'  // print error
				, self::$error[ $type ] ?: 'Error'
				, $php_errormsg
				, $file
				, $line
			);
		if ( $type & self::$reporting ) // print context
			echo self::context( $stack, $scope );
		echo '</pre>';
		if ( $type & E_USER_ERROR ) // fatal
			exit;
	}
	static public    function shutdown () {
		if ( self::$registered[ 'shutdown' ] && ( $error = error_get_last() ) && ( $error[ 'type' ] & E_ERROR |  E_PARSE | E_CORE_ERROR | E_COMPILE_ERROR ) )
			self::handler( $error[ 'type' ], $error[ 'message' ], $error[ 'file' ], $error[ 'line' ], null );
	}
	static public    function exception ( Exception $exception ) {
		$msg = sprintf( '"%s" with message "%s"', get_class( $exception ), $exception->getMessage() );
		self::handler( -1, $msg, $exception->getFile(), $exception->getLine(), null, $exception->getTrace() );
	}
	static public    $style     = array(
		'debug'         => 'font-size:1em'
		, 'error'       => 'background:#eee;padding:.5em'
		, 'exception'   => 'color:#825'
		//, 'parse'       => 'color:#F07'
		//, 'compile'     => 'color:#F70'
		, 'fatal'       => 'color:#F00'
		, 'recoverable' => 'color:#F22'
		, 'warning'     => 'color:#E44'
		, 'notice'      => 'color:#E66'
		, 'deprecated'  => 'color:#F88'
		, 'strict'      => 'color:#FAA'
		, 'stack'       => 'padding:.2em .8em;color:#444'
		, 'trace'       => 'border-left:1px solid #ccc;padding-left:1em'
		, 'scope'       => 'padding:.2em .8em;color:#666'
		, 'var'         => 'padding-left:1em'
		, 'chrono'      => 'border-left:2px solid #ccc'
		, 'init'        => 'color:#4A6'
		, 'time'        => 'color:#284'
		, 'table'       => 'color:#042'
	);
	static protected function style () {
		static $style;
		if ( $style )
			return;
		foreach ( self::$style as $class => $css )
			$style .= sprintf( '.%s{%s}', $class, $css );
		return PHP_EOL . '<style type="text/css">' . $style . '</style>';
	}
	static protected $overload  = array(
		'__callStatic'   => 2
		, '__call'       => 2
		, '__get'        => 1
		, '__set'        => 1
		, '__clone'      => 1
		, 'offsetGet'    => 1
		, 'offsetSet'    => 1
		, 'offsetUnset'  => 1
		, 'offsetExists' => 1
	);
	static protected function overload ( &$stack, &$file, &$line ) {
		if ( isset( $stack[ 0 ][ 'class' ], self::$overload[ $stack[ 0 ][ 'function' ] ] ) && $offset = self::$overload[ $stack[ 0 ][ 'function' ] ] )
			for ( $i = 0; $i < $offset; $i++ )
				extract( array_shift( $stack ) ); // clean stack and overwrite file & line
	}
	static protected function context ( $stack, $scope ) {
		if ( ! $stack )
				return;
		$context[] = PHP_EOL . '<div class="stack"><i>Stack trace</i> :';
		foreach ( $stack as $index => $call )
			$context[] = sprintf( '  <span class="trace">#%s %s: %s%s%s(%s)</span>'
				, $index
				, isset( $call[ 'file' ] )  ? $call[ 'file' ] . ' (' . $call[ 'line' ] . ')' : '[internal function]'
				, isset( $call[ 'class' ] ) ? $call[ 'class' ]                              : ''
				, isset( $call[ 'type' ] )  ? $call[ 'type' ]                               : ''
				, $call[ 'function' ]
				, isset( $call[ 'args' ] )  ? self::args_export( $call[ 'args' ] )          : ''
			);
		$context[] = '  <span class="trace">#' . ( $index + 1 ) . ' {main}</span>'; 
		$context[] = '</div><div class="scope"><i>Scope</i> :';
		if ( isset( $scope['GLOBALS'] ) )
			$context[] = '  GLOBAL';
		elseif ( ! $scope )
			$context[] = '  NONE';
		else
			foreach ( (array) $scope as $name => $value )
				$context[] = '  <span class="var">$' . $name .' = ' . self::var_export( $value ) . ';</span>';
		$context[] = '</div>';
		return implode( PHP_EOL, $context );
	}
	static protected function var_export ( $var ) {
		$export = var_export( $var, true );
		if ( is_scalar( $var ) )
				return $export;
		if ( is_object( $var ) ) {
			$pattern = '#::__set_state\(array(\((?:[^()]+|(?1))*\))\)#m';
			while ( preg_match( $pattern, $export ) )
				$export = preg_replace( $pattern, ' $1', $export );
		}
		$export = preg_replace( '#\(\s+\)#m','()', $export );
		$export = preg_replace( '# =>\s+#m',' => ', $export );
		return preg_replace( '#,(\s+\))#m','$1', $export );
	}
	static protected function simple_export ( $var ) {
		if ( is_array( $var ) )
			return 'array(' . self::args_export( $var ) . ')';
		$export = var_export( $var, true );
		if ( is_object( $var ) )
			return 'object(' . strstr( $export, '::', true ) . ')';
		return $export;
	}
	static protected function args_export ( $args ) {
		return implode(', ', array_map( 
			'Debug::simple_export', 
			(array) $args
		) );
	}
}
Debug::register();
function l () {
	call_user_func_array( 'Debug::log', func_get_args() );
}
function d () {
	call_user_func_array( 'Debug::dump', func_get_args() );
}
function c () {
	call_user_func_array( 'Debug::chrono', func_get_args() );
}