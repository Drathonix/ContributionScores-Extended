# Contribution Scores
The Contribution Scores extension polls the wiki database to locate contributors with the highest contribution volume.
The extension is intended to add a fun metric for contributors to see how much they are helping out.

# Changes in this fork
1. Added absdiff metric for cscore. Absdiff takes the total number of characters/bytes changed throughout a user's entire lifetime or a specified number of days and sums them together. Absdiff can be accessed outside of the Top Contributor's page using "{{#cscore:<USER>|absdiff}}".
2. Changed the score calculation from #unique_pages_editted+sqrt(#changes) to absdiff/100+2*#unique_pages_editted. This effectively reverses the scoring from prioritizing #unique_pages_editted to the actual content of the edits.
3. Added the "Diff" column to the Contribution Scores page generator. This presents absdiff. Also this may need translation, currently it is only declared in the en lang file.
