<?php

return [
    /***********************************************************************************************************************
     * You can use this to set the NICKNAME=>REALNAME and USERNAME below.
     * You can change them manually below if you have to.
     * @note THIS MUST NOT BE EMPTY=>THIS MUST ALSO BE UNIQUE OR YOU WILL NOT BE ABLE TO CONNECT TO IRC.
     * @note pick a normal name otherwise you will be banned from the pre channel !!!!
     **********************************************************************************************************************/
    'username' => env('SCRAPE_IRC_USERNAME', ''),

    /***********************************************************************************************************************
     * The IRC server to connect to.
     * @note If you have issues connecting=>head to https://www.synirc.net/servers and try another server.
     **********************************************************************************************************************/
    'scrape_irc_server' => env('SCRAPE_IRC_SERVER', 'irc.synirc.net'),

    /***********************************************************************************************************************
     * This is the port to the IRC server.
     * @note If you want use SSL/TLS=>use a corresponding port (6697 or 7001 for example)=>and set SCRAPE_IRC_TLS to true.
     **********************************************************************************************************************/
    'scrape_irc_port' => env('SCRAPE_IRC_PORT', 6667),

    /***********************************************************************************************************************
     * If you want to use SSL/TLS encryption on the IRC server=>set this to true.
     * @note Make sure you use a valid SSL/TLS port in SCRAPE_IRC_PORT.
     **********************************************************************************************************************/
    'scrape_irc_tls' => env('SCRAPE_IRC_TLS', false),

    /***********************************************************************************************************************
     * This is the nick name visible in IRC channels.
     **********************************************************************************************************************/
    'scrape_irc_nickname' => env('SCRAPE_IRC_USERNAME', ''),

    /***********************************************************************************************************************
     * This is a name that is visible to others when they type /whois nickname.
     **********************************************************************************************************************/
    'scrape_irc_realname' => env('SCRAPE_IRC_USERNAME', ''),

    /***********************************************************************************************************************
     * This is used as part of your "ident" when connecting to IRC.
     * @note This is also the username for ZNC.
     **********************************************************************************************************************/
    'scrape_irc_username' => env('SCRAPE_IRC_USERNAME', ''),

    /***********************************************************************************************************************
     * This is not required by synirc=>but if you use ZNC=>this is required.
     * @note Put your password between quotes: 'mypassword'
     * @note If you are using ZNC and having issues=>try 'username:password' or 'username/network:<password>'
     **********************************************************************************************************************/
    'scrape_irc_password' => env('SCRAPE_IRC_PASSWORD', false),

    /***********************************************************************************************************************
     * This is an optional field you can use for ignoring categories.
     * @note If you do not wish to exclude any categories=>leave it a empty string: ''
     * @examples Case sensitive:   '/^(XXX|PDA|EBOOK|MP3)$/'
     *           Case insensitive: '/^(X264|TV)$/i'
     **********************************************************************************************************************/
    'scrape_irc_category_ignore' => '',

    /***********************************************************************************************************************
     * This is an optional field you can use for ignoring PRE titles.
     * @note If you do not wish to exclude any PRE titles=>leave it a empty string: ''
     * @examples Case insensitive ignore German or XXX in the title: '/\.(German|XXX)\./i'
     *           This would ignore titles like:
     *           Yanks.14.06.30.Bianca.Travelman.Is.A.Nudist.XXX.MP4-FUNKY
     *           Blancanieves.Ein.Maerchen.von.Schwarz.und.Weiss.2012.German.1080p.BluRay.x264-CONTRiBUTiON
     **********************************************************************************************************************/
    'scrape_irc_title_ignore' => '',

    /***********************************************************************************************************************
     * This is a list of all the channels we fetch PRE's from.
     **********************************************************************************************************************/

    'scrape_irc_channels' => serialize(
        [
            // '#Channel'                => 'Password',
            '#PreNNTmux' => null,
            '#nZEDbPRE' => null,
        ]
    ),

    /***********************************************************************************************************************
     * This is a list of all the sources we fetch PRE's from.
     * If you want to ignore a source=>change it from false to true.
     **********************************************************************************************************************/

    'scrape_irc_source_ignore' => serialize(
        [
            '#a.b.cd.image' => false,
            '#a.b.console.ps3' => false,
            '#a.b.dvd' => false,
            '#a.b.erotica' => false,
            '#a.b.flac' => false,
            '#a.b.foreign' => false,
            '#a.b.games.nintendods' => false,
            '#a.b.inner-sanctum' => false,
            '#a.b.moovee' => false,
            '#a.b.movies.divx' => false,
            '#a.b.sony.psp' => false,
            '#a.b.sounds.mp3.complete_cd' => false,
            '#a.b.teevee' => false,
            '#a.b.games.wii' => false,
            '#a.b.warez' => false,
            '#a.b.games.xbox360' => false,
            '#pre@corrupt' => false,
            '#scnzb' => false,
            '#tvnzb' => false,
            'srrdb' => false,
        ]
    ),
];
