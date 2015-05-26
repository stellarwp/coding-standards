<?php
if ( ! $argv )
{
	die( "\n\n==============\nYou must provide an absolute path to wp-includes/deprecated.php as the first parameter\n================\n\n" );
}//end if

$deprecated_file = $argv[1];

$file  = file( $deprecated_file );
$function_replace = array();

$seek_function       = false;
$deprecated_function = null;
$good_function       = null;

foreach ( $file as $line )
{
	if ( ! $seek_function )
	{
		if ( preg_match( '/\@deprecated [Uu]se (.+)/', $line, $matches ) )
		{
			$seek_function = true;
			$good_function = $matches[1];
		}//end if

		if ( preg_match( '/^function ([^\(]+)/', $line, $matches ) )
		{
			$function_replace[ $matches[1] ] = null;
			$good_function                   = null;
		}//end if
	}//end if
	else
	{
		if ( preg_match( '/function ([^\(]+)/', $line, $matches ) )
		{
			$seek_function                   = false;
			$function_replace[ $matches[1] ] = $good_function;
			$good_function                   = null;
		}//end if
	}//end else
}//end foreach

foreach ( $function_replace as $bad => $good )
{
	$good = str_replace( '$', '\$', $good );
	if ( $good )
	{
		echo "\"{$bad}\" => \"{$good}\",\n";
	}//end if
	else
	{
		echo "\"{$bad}\" => null,\n";
	}//end else
}//end foreach
