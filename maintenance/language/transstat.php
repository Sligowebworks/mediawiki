<?php
/**
 * Statistics about the localisation.
 *
 * @package MediaWiki
 * @subpackage Maintenance
 *
 * @author Ævar Arnfjörð Bjarmason <avarab@gmail.com>
 * @author Ashar Voultoiz <thoane@altern.org>
 *
 * Output is posted from time to time on:
 * http://meta.wikimedia.org/wiki/Localization_statistics
 */

require_once( dirname(__FILE__).'/../commandLine.inc' );
require_once( 'languages.inc' );

if ( isset( $options['help'] ) ) {
	showUsage();
}

# Default output is WikiText
if ( !isset( $options['output'] ) ) {
	$options['output'] = 'wiki';
}

/** Print a usage message*/
function showUsage() {
	print <<<END
Usage: php transstat.php [--help] [--output=csv|text|wiki]
	--help : this helpful message
	--output : select an output engine one of:
		* 'csv'      : Comma Separated Values.
		* 'wiki'     : MediaWiki syntax (default).
		* 'metawiki' : MediaWiki syntax used for Meta-Wiki.
		* 'text'     : Text with tabs.
Example: php maintenance/transstat.php --output=text

END;
	exit();
}

/** A general output object. Need to be overriden */
class statsOutput {
	function formatPercent( $subset, $total, $revert = false, $accuracy = 2 ) {
		return @sprintf( '%.' . $accuracy . 'f%%', 100 * $subset / $total );
	}

	# Override the following methods
	function heading() {
	}
	function footer() {
	}
	function blockstart() {
	}
	function blockend() {
	}
	function element( $in, $heading = false ) {
	}
}

/** Outputs WikiText */
class wikiStatsOutput extends statsOutput {
	function heading() {
		global $IP;
		$version = SpecialVersion::getVersion( $IP );
		echo "'''Statistics are based on:''' <code>" . $version . "</code>\n\n";
		echo "'''Note:''' These statistics can be generated by running <code>php maintenance/language/transstat.php</code>.\n\n";
		echo "For additional information on specific languages (the message names, the actual problems, etc.), run <code>php maintenance/language/checkLanguage.php --lang=foo</code>.\n\n";
		echo '{| class="sortable wikitable" border="2" cellpadding="4" cellspacing="0" style="background-color: #F9F9F9; border: 1px #AAAAAA solid; border-collapse: collapse;" width="100%"'."\n";
	}
	function footer() {
		echo "|}\n";
	}
	function blockstart() {
		echo "|-\n";
	}
	function blockend() {
		echo '';
	}
	function element( $in, $heading = false ) {
		echo ($heading ? '!' : '|') . " $in\n";
	}
	function formatPercent( $subset, $total, $revert = false, $accuracy = 2 ) {
		$v = @round(255 * $subset / $total);
		if ( $revert ) {
			$v = 255 - $v;
		}
		if ( $v < 128 ) {
			# Red to Yellow
			$red = 'FF';
			$green = sprintf( '%02X', 2 * $v );
		} else {
			# Yellow to Green
			$red = sprintf('%02X', 2 * ( 255 - $v ) );
			$green = 'FF';
		}
		$blue = '00';
		$color = $red . $green . $blue;

		$percent = statsOutput::formatPercent( $subset, $total, $revert, $accuracy );
		return 'bgcolor="#'. $color .'" | '. $percent;
	}
}

/** Outputs WikiText and appends category and text only used for Meta-Wiki */
class metawikiStatsOutput extends wikiStatsOutput {
	function heading() {
		echo "See [[MediaWiki localisation]] to learn how you can help translating MediaWiki.\n\n";
		parent::heading();
	}
	function footer() {
		parent::footer();
		echo "\n[[Category:Localisation|Statistics]]\n";
	}
}

/** Output text. To be used on a terminal for example. */
class textStatsOutput extends statsOutput {
	function element( $in, $heading = false ) {
		echo $in."\t";
	}
	function blockend() {
		echo "\n";
	}
}

/** csv output. Some people love excel */
class csvStatsOutput extends statsOutput {
	function element( $in, $heading = false ) {
		echo $in . ";";
	}
	function blockend() {
		echo "\n";
	}
}

# Select an output engine
switch ( $options['output'] ) {
	case 'wiki':
		$wgOut = new wikiStatsOutput();
		break;
	case 'metawiki':
		$wgOut = new metawikiStatsOutput();
		break;
	case 'text':
		$wgOut = new textStatsOutput();
		break;
	case 'csv':
		$wgOut = new csvStatsOutput();
		break;
	default:
		showUsage();
}

# Languages
$wgLanguages = new languages();

# Header
$wgOut->heading();
$wgOut->blockstart();
$wgOut->element( 'Language', true );
$wgOut->element( 'Code', true );
$wgOut->element( 'Translated', true );
$wgOut->element( '%', true );
$wgOut->element( 'Obsolete', true );
$wgOut->element( '%', true );
$wgOut->element( 'Problematic', true );
$wgOut->element( '%', true );
$wgOut->blockend();

$wgGeneralMessages = $wgLanguages->getGeneralMessages();
$wgRequiredMessagesNumber = count( $wgGeneralMessages['required'] );

foreach ( $wgLanguages->getLanguages() as $code ) {
	# Don't check English or RTL English
	if ( $code == 'en' || $code == 'enRTL' ) {
		continue;
	}

	# Calculate the numbers
	$language = $wgContLang->getLanguageName( $code );
	$messages = $wgLanguages->getMessages( $code );
	$messagesNumber = count( $messages['translated'] );
	$requiredMessagesNumber = count( $messages['required'] );
	$requiredMessagesPercent = $wgOut->formatPercent( $requiredMessagesNumber, $wgRequiredMessagesNumber );
	$obsoleteMessagesNumber = count( $messages['obsolete'] );
	$obsoleteMessagesPercent = $wgOut->formatPercent( $obsoleteMessagesNumber, $messagesNumber, true );
	$messagesWithoutVariables = $wgLanguages->getMessagesWithoutVariables( $code );
	$emptyMessages = $wgLanguages->getEmptyMessages( $code );
	$messagesWithWhitespace = $wgLanguages->getMessagesWithWhitespace( $code );
	$nonXHTMLMessages = $wgLanguages->getNonXHTMLMessages( $code );
	$messagesWithWrongChars = $wgLanguages->getMessagesWithWrongChars( $code );
	$problematicMessagesNumber = count( array_unique( array_merge( $messagesWithoutVariables, $emptyMessages, $messagesWithWhitespace, $nonXHTMLMessages, $messagesWithWrongChars ) ) );
	$problematicMessagesPercent = $wgOut->formatPercent( $problematicMessagesNumber, $messagesNumber, true );

	# Output them
	$wgOut->blockstart();
	$wgOut->element( "$language" );
	$wgOut->element( "$code" );
	$wgOut->element( "$requiredMessagesNumber/$wgRequiredMessagesNumber" );
	$wgOut->element( $requiredMessagesPercent );
	$wgOut->element( "$obsoleteMessagesNumber/$messagesNumber" );
	$wgOut->element( $obsoleteMessagesPercent );
	$wgOut->element( "$problematicMessagesNumber/$messagesNumber" );
	$wgOut->element( $problematicMessagesPercent );
	$wgOut->blockend();
}

# Footer
$wgOut->footer();

?>
