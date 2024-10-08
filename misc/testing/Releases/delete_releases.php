<?php

require_once dirname(__DIR__, 3).DIRECTORY_SEPARATOR.'bootstrap/autoload.php';

use Blacklight\ColorCLI;
use Blacklight\ReleaseRemover;

// New line for CLI.
$n = PHP_EOL;

// Include config.php

// ColorCLI class.
$cli = new ColorCLI;

// Print arguments/usage.
$totalArgs = count($argv);
if ($totalArgs < 2) {
    exit($cli->info(
        $n.
        'This deletes releases based on a list of criteria you pass.'.$n.
        'Usage:'.$n.$n.
        'List of supported criteria:'.$n.
        'fromname   : Look for names of people who posted releases (the poster name). (modifiers: equals, like)'.$n.
        'groupname  : Look in groups. (modifiers: equals, like)'.$n.
        'guid       : Look for a specific guid. (modifiers: equals)'.$n.
        'name       : Look for a name (the usenet name). (modifiers: equals, like)'.$n.
        'searchname : Look for a name (the search name). (modifiers: equals, like)'.$n.
        'size       : Release must be (bigger than |smaller than |exactly) this size.(bytes) (modifiers: equals,bigger,smaller)'.$n.
        'adddate    : Look for releases added to our DB (older than|newer than) x hours. (modifiers: bigger,smaller)'.$n.
        'postdate   : Look for posted to usenet (older than|newer than) x hours. (modifiers: bigger,smaller)'.$n.
        'completion : Look for completion (less than) (modifiers: smaller)'.$n.
        'categories_id : Look for releases within specified category (modifiers: equals)'.$n.
        'imdbid     : Look for releases with imdbid (modifiers: equals)'.$n.
        'rageid     : Look for releases with rageid (modifiers: equals)'.$n.
        'totalpart  : Look for releases with certain number of parts (modifiers: equals,bigger,smaller)'.$n.
        'nzbstatus  : Look for releases with nzbstatus (modifiers: equals)'.$n.$n.
        'List of Modifiers:'.$n.
        'equals     : Match must be exactly this. (fromname=equals="john" will only look for "john", not "johndoe")'.$n.
        'like       : Match can be similar to this. Separate words using spaces(ie:"cars hdtv x264").'.$n.
        '             (fromname=like="john" will look for any posters with john in it (ie:john@smith.com)'.$n.
        'bigger     : Match must be bigger than this. (postdate=bigger="3" means older than 3 hours ago)'.$n.
        'smaller    : Match must be smaller than this (postdate=smaller="3" means between now and 3 hours ago.'.$n.$n.
        'Extra:'.$n.
        'ignore     : Ignore the user check. (before running we ask you if you want to run the query to delete)'.$n.$n.
        'Examples:'.$n.
        $_SERVER['_'].' '.$argv[0].' groupname=equals="alt.binaries.teevee" searchname=like="olympics 2014" postdate=bigger="5"'.$n.
        $_SERVER['_'].' '.$argv[0].' guid=equals="8fb5956bae3de4fb94edcc69da44d6883d586fd0"'.$n.
        $_SERVER['_'].' '.$argv[0].' size=smaller="104857600" size=bigger="2048" groupname=like="movies"'.$n.
        $_SERVER['_'].' '.$argv[0].' fromname=like="@XviD.net" groupname=equals="alt.binaries.movies.divx" ignore'.$n.
        $_SERVER['_'].' '.$argv[0].' imdbid=equals=NULL categories_id=equals=2999 nzbstatus=equals=1 adddate=bigger=2880 # Remove other movie releases with non-cleaned names added > 120 days ago'
    ));
}

$RR = new ReleaseRemover;
// Remove argv[0] and send the array.
$RR->removeByCriteria(array_slice($argv, 1, $totalArgs - 1));
