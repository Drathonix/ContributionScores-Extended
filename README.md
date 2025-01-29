# Contribution Scores
The Contribution Scores extension polls the wiki database to locate contributors with the highest contribution volume.
The extension is intended to add a fun metric for contributors to see how much they are helping out.

# Changes in this fork
1. Added absdiff metric for cscore. Absdiff takes the total length of the characters/bytes changed throughout a user's entire lifetime or a specified number of days and sums them together. Absdiff can be accessed outside of the Top Contributor's page using "{{#cscore:\<USER\>|absdiff}}". As an example changing "a" to "aaaa" has an absdiff value of 3, changing "a" to "bbbb" also has a absdiff value of 3 because the length of the change is the same.
2. Changed the score calculation from #unique_pages_editted+sqrt(#changes) to absdiff/100+2*#unique_pages_editted. This effectively reverses the scoring from prioritizing #unique_pages_editted to the actual content of the edits.
3. Added the "Diff" column to the Contribution Scores page generator. This presents absdiff. Also this may need translation, currently it is only declared in the en lang file.
4. Added the creations metric for cscore. Creations is simply the number of pages a user has created.
5. A large amount of the database interaction code has been modified and abstracted for each metric.

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
