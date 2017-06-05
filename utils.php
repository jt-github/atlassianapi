<?php
class Utils {

	public static function DateChangeZone($date, $origTZ = 'UTC', $destTZ = 'America/Chicago') {
		$d = new DateTime($date, new DateTimeZone($origTZ));
		$d->setTimezone(new DateTimeZone($destTZ));
		return $d;
	}
	
	// Returns a "friendly" output of an Interval
	// Accuracy refers to how many kinds of periods are provided,
	// 			starting with the biggest one in the Interval.
	public static function IntervalFriendly($interval, $accuracy = 3) {
		$format = array(); 
		if($interval->y !== 0) { 
			$format[] = "%y " . self::Pluralize($interval->y, "year"); 
		} 
		if($interval->m !== 0) { 
			$format[] = "%m " . self::Pluralize($interval->m, "month"); 
		} 
		if($interval->d !== 0) { 
			$format[] = "%d " . self::Pluralize($interval->d, "day"); 
		} 
		if($interval->h !== 0) { 
			$format[] = "%h " . self::Pluralize($interval->h, "hour"); 
		} 
		if($interval->i !== 0) { 
			$format[] = "%i " . self::Pluralize($interval->i, "minute"); 
		} 
		if($interval->s !== 0) { 
			$format[] = "%s " . self::Pluralize($interval->s, "second"); 
		}
		if (count($format) < $accuracy)
			$accuracy = count($format);
		$formatStr = '';
		for ($i = 1; $i <= $accuracy; $i++) {
			$formatStr .= array_shift($format);
			if ($i < $accuracy)
				$formatStr .= ", "; 
		}
		return $interval->format($formatStr); 
	}
	
	public static function Pluralize($nb,$str) {
		return $nb > 1 ? $str . 's' : $str;
	} 

	// Disable Catching Warnings
	public static function WarningHandlerDisable() {
		restore_error_handler();
	}
	
	// Enable Catching Warnings
	public static function WarningHandlerEnable() {
		set_error_handler(function($errno, $errstr, $errfile, $errline, array $errcontext) {
			// error was suppressed with the @-operator
			if (0 === error_reporting()) {
				return false;
			}
			throw new ErrorException($errstr, 0, $errno, $errfile, $errline);
		});
	}
	
}
?>