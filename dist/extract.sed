#!/usr/bin/sed
# extract needed php code out of includes

# delete everything that's not between those tags
/STARTVACCINATOR/,/ENDVACCINATOR/!d
# remove the tags themselves
/STARTVACCINATOR/d
/ENDVACCINATOR/d
# trim trailing spaces
s/^\(.*\S\)\s*$/\1/
