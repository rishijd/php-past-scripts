## These scripts were auto-generated by a custom-built CMS. 
The scripts DO NOT reflect my coding style as they are purposely minified to a readable extent.

The e-commerce site had many languages over the years, 
but was available in German (1), Dutch (2), British English (6), French (7) 
and U.S. English (not shown as this was custom-built to use British English except 
when certain language variables were overridden by the client, via the custom CMS). 
For example, in the UK we may use "ladies boots" but in the US for SEO we used
"womens boots", and so language did vary from the two sites, UK being the master.

We also had several language files, but just one of them (per language) 
is shown in this folder, to get a sense of how this was implemented. 
We previously used constants for some language files, but found that mutable structures
like arrays were easier to manipulate because manipulation was sometimes required on 
the language for certain sites (e.g. US overriding UK language variables in certain
instances, plus more complex situations).