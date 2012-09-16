=== Audio ===
Contributors: wonderboymusic
Tags: rewrite, date, permalinks
Requires at least: 3.0
Tested up to: 3.5
Stable Tag: 0.1

Fixes rewrites for non-Big Endian date permastructs

== Description ==

DATES BY ENDIANNESS (http://{SITE}/{DATE}/hello-world/)

Regardless of your post permastruct, date archives are generated. Post rewrites completely clobber date rewrites. Date rewrites are broken already, but even when fixed, they can be overwritten by post rewrites. This plugin fixes date archives and persists them past post overwrites - supporting every possible flavor of endian, and every logical combination of %year%, %monthnum%, %day% within them. Below is a matrix of what is possible, the things which do not work in core are marked.

---------------- 

!!! = Doesn't work without this plugin

----------------

Big Endian in both directions

2012/09/12 yyyy/mm/dd
2012/09 yyyy/mm
2012 yyyy
12 dd !!!
09/12 mm/dd !!!

----------------

Reverse of Middle Endian in both directions

2012/12/09 yyyy/dd/mm
2012/09 yyyy/mm !!! ~ matches day
2012 yyyyy
09 mm !!! 
12/09 dd/mm !!!

----------------

Middle Endian in both directions

09/12/2012 mm/dd/yyyy
09/12 mm/dd
09 mm
2012 yyyy !!!
09/2012 mm/yyyy !!!

----------------

Little Endian in both directions

12/09/2012 dd/mm/yyyy
12/09 dd/yy
12 dd
2012 yyyy !!!
09/2012 mm/yyyy !!!

----------------

Translated:

Year and Month archives don't work when you date format is Middle or Little Endian.
Year archives work in Middle when reversed (reversed, why not), but months do not.

This plugin makes all date formats work regardless of endianness.


== Changelog ==

= 0.1 =
* Initial release

== Upgrade Notice ==