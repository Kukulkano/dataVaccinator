base := $(shell dirname $(realpath $(lastword $(MAKEFILE_LIST))))

WANT_DOCMAKER=1
VERSION?=0.2.0

all: pdf

PRODUCT=vaccinator
# The input file (without path and .xml)
MANUA=vaccinator-protocol

DOC_FILE=$(base)/$(MANUA).xml
DOC_OUTFILE=$(base)/$(MANUA).pdf
DOC_LOGO=$(RF_INCLUDES)/images/docbook_logo.png

# Let docmaker replace @product@, @PRODUCT@ and set the condition parameter
DOC_EXTRA_ARGS=-e"@LOGO@=>$(DOC_LOGO)" -e"@PRODUCT@=>$(PRODUCT)"

include $(RF_INCLUDES)/common.mk

pdf:
	make docmaker

clean:
	make docmakerclean
