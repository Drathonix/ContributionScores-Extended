# Contribution Scores Extended
The Contribution Scores extension polls the wiki database to locate contributors with the highest contribution volume.
The extension is intended to add a fun metric for contributors to see how much they are helping out.

The original CScores is by Tim Lagua.
Extended is by Jack Andersen.

# Installation and Upgrading
Note: You will need to update both the extension and LocalSettings.php for Extended to work.

At the bottom of `LocalSettings.php` add
```
wfLoadExtension( 'ContributionScores' );
// Exclude Bots from the reporting - Can be omitted.
$wgContribScoreIgnoreBots = true; 
// Exclude Blocked Users from the reporting - Can be omitted.
$wgContribScoreIgnoreBlockedUsers = true;
// Exclude specific usernames from the reporting - Can be omitted.
$wgContribScoreIgnoreUsernames = [];
// Use real user names when available - Can be omitted. Only for MediaWiki 1.19 and later.
$wgContribScoresUseRealName = true;
// Set it to true to disable the cache for the parser function and the inclusion of the table.
$wgContribScoreDisableCache = true;
// Use the total edit count to compute the Contribution score.
$wgContribScoreUseRoughEditCount = false;   
// Each array defines a report - 7,50 is "past 7 days," and "LIMIT 50" - Can be omitted.
$wgContribScoreReports = [
    [ 7, 50 ],
    [ 30, 50 ],
    [ 0, 50 ]
];
//Extended features start here.
// Exclude specific pages from the reporting - Can be omitted. Must use SQL syntax supported by the "LIKE" operator
$wgContribScoreTitleFilters = [];
// Exclude specific page NAMESPACES from the reporting - Must use the namespace numerical id as seen here: https://www.mediawiki.org/wiki/Manual:Namespace. By default all "Talk" namespaces are excluded.
$wgContribScorePageNamespaceFilters = [1,3,5,7,9,11,13,15];
```
Tweak these settings as you wish!
Then, place the `ContributionScores` folder in the `extensions` directory like you would any other!
Perform any necessary restarts and enjoy!

# Extended features
1. Added absdiff metric for cscore. Absdiff takes the total length of the characters/bytes changed throughout a user's entire lifetime or a specified number of days and sums them together. Absdiff can be accessed outside of the Top Contributor's page using "{{#cscore:\<USER\>|absdiff}}". As an example changing "a" to "aaaa" has an absdiff value of 3, changing "a" to "bbbb" also has a absdiff value of 3 because the length of the change is the same.
2. Changed the score calculation from #unique_pages_editted+sqrt(#changes) to absdiff/100+2*#unique_pages_editted. This effectively reverses the scoring from prioritizing #unique_pages_editted to the actual content of the edits.
3. Added the "Diff" column to the Contribution Scores page generator. This presents absdiff. Also this may need translation, currently it is only declared in the en lang file.
4. Added the creations metric for cscore. Creations is simply the number of pages a user has created.
5. A large amount of the database interaction code has been modified and abstracted for each metric.
6. Added filters for page titles and page namespaces to config for easy exclusion of specific pages from being included in Contribution Scores calc.
7. README > README.md :fire:

# Overview
## Scoring Metrics
### \{\{\#cscore:\<user\>|score\}\}
Displays the user's calculated score. Score is calculated by `absdiff/100 + unique_pages*2` by default.
### \{\{\#cscore:\<user\>|pages\}\}
Displays the number of unique pages the user has editted.
### \{\{\#cscore:\<user\>|changes\}\}
Displays the number of revisions the user has submitted.
### \{\{\#cscore:\<user\>|absdiff\}\}
Displays the total difference in character length between all the user's revisions. As an example changing "a" to "bbbb" yields an absdiff of 3.
### \{\{\#cscore:\<user\>|creations\}\}
The total number of pages the user has created.
