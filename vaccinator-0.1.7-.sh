#!/bin/sh
# This script was generated using Makeself 2.3.0

ORIG_UMASK=`umask`
if test "n" = n; then
    umask 077
fi

CRCsum="631576612"
MD5="478c94811147b67263676ebed70ba5fb"
TMPROOT=${TMPDIR:=/tmp}
USER_PWD="$PWD"; export USER_PWD

label="vaccinator 0.1.7-"
script="./install.sh"
scriptargs=""
licensetxt=""
helpheader=''
targetdir="vaccinator-0.1.7"
filesizes="12792"
keep="n"
nooverwrite="n"
quiet="n"

print_cmd_arg=""
if type printf > /dev/null; then
    print_cmd="printf"
elif test -x /usr/ucb/echo; then
    print_cmd="/usr/ucb/echo"
else
    print_cmd="echo"
fi

unset CDPATH

MS_Printf()
{
    $print_cmd $print_cmd_arg "$1"
}

MS_PrintLicense()
{
  if test x"$licensetxt" != x; then
    echo "$licensetxt"
    while true
    do
      MS_Printf "Please type y to accept, n otherwise: "
      read yn
      if test x"$yn" = xn; then
        keep=n
	eval $finish; exit 1
        break;
      elif test x"$yn" = xy; then
        break;
      fi
    done
  fi
}

MS_diskspace()
{
	(
	if test -d /usr/xpg4/bin; then
		PATH=/usr/xpg4/bin:$PATH
	fi
	df -kP "$1" | tail -1 | awk '{ if ($4 ~ /%/) {print $3} else {print $4} }'
	)
}

MS_dd()
{
    blocks=`expr $3 / 1024`
    bytes=`expr $3 % 1024`
    dd if="$1" ibs=$2 skip=1 obs=1024 conv=sync 2> /dev/null | \
    { test $blocks -gt 0 && dd ibs=1024 obs=1024 count=$blocks ; \
      test $bytes  -gt 0 && dd ibs=1 obs=1024 count=$bytes ; } 2> /dev/null
}

MS_dd_Progress()
{
    if test x"$noprogress" = xy; then
        MS_dd $@
        return $?
    fi
    file="$1"
    offset=$2
    length=$3
    pos=0
    bsize=4194304
    while test $bsize -gt $length; do
        bsize=`expr $bsize / 4`
    done
    blocks=`expr $length / $bsize`
    bytes=`expr $length % $bsize`
    (
        dd ibs=$offset skip=1 2>/dev/null
        pos=`expr $pos \+ $bsize`
        MS_Printf "     0%% " 1>&2
        if test $blocks -gt 0; then
            while test $pos -le $length; do
                dd bs=$bsize count=1 2>/dev/null
                pcent=`expr $length / 100`
                pcent=`expr $pos / $pcent`
                if test $pcent -lt 100; then
                    MS_Printf "\b\b\b\b\b\b\b" 1>&2
                    if test $pcent -lt 10; then
                        MS_Printf "    $pcent%% " 1>&2
                    else
                        MS_Printf "   $pcent%% " 1>&2
                    fi
                fi
                pos=`expr $pos \+ $bsize`
            done
        fi
        if test $bytes -gt 0; then
            dd bs=$bytes count=1 2>/dev/null
        fi
        MS_Printf "\b\b\b\b\b\b\b" 1>&2
        MS_Printf " 100%%  " 1>&2
    ) < "$file"
}

MS_Help()
{
    cat << EOH >&2
${helpheader}Makeself version 2.3.0
 1) Getting help or info about $0 :
  $0 --help   Print this message
  $0 --info   Print embedded info : title, default target directory, embedded script ...
  $0 --lsm    Print embedded lsm entry (or no LSM)
  $0 --list   Print the list of files in the archive
  $0 --check  Checks integrity of the archive

 2) Running $0 :
  $0 [options] [--] [additional arguments to embedded script]
  with following options (in that order)
  --confirm             Ask before running embedded script
  --quiet		Do not print anything except error messages
  --noexec              Do not run embedded script
  --keep                Do not erase target directory after running
			the embedded script
  --noprogress          Do not show the progress during the decompression
  --nox11               Do not spawn an xterm
  --nochown             Do not give the extracted files to the current user
  --target dir          Extract directly to a target directory
                        directory path can be either absolute or relative
  --tar arg1 [arg2 ...] Access the contents of the archive through the tar command
  --                    Following arguments will be passed to the embedded script
EOH
}

MS_Check()
{
    OLD_PATH="$PATH"
    PATH=${GUESS_MD5_PATH:-"$OLD_PATH:/bin:/usr/bin:/sbin:/usr/local/ssl/bin:/usr/local/bin:/opt/openssl/bin"}
	MD5_ARG=""
    MD5_PATH=`exec <&- 2>&-; which md5sum || command -v md5sum || type md5sum`
    test -x "$MD5_PATH" || MD5_PATH=`exec <&- 2>&-; which md5 || command -v md5 || type md5`
	test -x "$MD5_PATH" || MD5_PATH=`exec <&- 2>&-; which digest || command -v digest || type digest`
    PATH="$OLD_PATH"

    if test x"$quiet" = xn; then
		MS_Printf "Verifying archive integrity..."
    fi
    offset=`head -n 521 "$1" | wc -c | tr -d " "`
    verb=$2
    i=1
    for s in $filesizes
    do
		crc=`echo $CRCsum | cut -d" " -f$i`
		if test -x "$MD5_PATH"; then
			if test x"`basename $MD5_PATH`" = xdigest; then
				MD5_ARG="-a md5"
			fi
			md5=`echo $MD5 | cut -d" " -f$i`
			if test x"$md5" = x00000000000000000000000000000000; then
				test x"$verb" = xy && echo " $1 does not contain an embedded MD5 checksum." >&2
			else
				md5sum=`MS_dd_Progress "$1" $offset $s | eval "$MD5_PATH $MD5_ARG" | cut -b-32`;
				if test x"$md5sum" != x"$md5"; then
					echo "Error in MD5 checksums: $md5sum is different from $md5" >&2
					exit 2
				else
					test x"$verb" = xy && MS_Printf " MD5 checksums are OK." >&2
				fi
				crc="0000000000"; verb=n
			fi
		fi
		if test x"$crc" = x0000000000; then
			test x"$verb" = xy && echo " $1 does not contain a CRC checksum." >&2
		else
			sum1=`MS_dd_Progress "$1" $offset $s | CMD_ENV=xpg4 cksum | awk '{print $1}'`
			if test x"$sum1" = x"$crc"; then
				test x"$verb" = xy && MS_Printf " CRC checksums are OK." >&2
			else
				echo "Error in checksums: $sum1 is different from $crc" >&2
				exit 2;
			fi
		fi
		i=`expr $i + 1`
		offset=`expr $offset + $s`
    done
    if test x"$quiet" = xn; then
		echo " All good."
    fi
}

UnTAR()
{
    if test x"$quiet" = xn; then
		tar $1vf - 2>&1 || { echo Extraction failed. > /dev/tty; kill -15 $$; }
    else

		tar $1f - 2>&1 || { echo Extraction failed. > /dev/tty; kill -15 $$; }
    fi
}

finish=true
xterm_loop=
noprogress=n
nox11=n
copy=none
ownership=y
verbose=n

initargs="$@"

while true
do
    case "$1" in
    -h | --help)
	MS_Help
	exit 0
	;;
    -q | --quiet)
	quiet=y
	noprogress=y
	shift
	;;
    --info)
	echo Identification: "$label"
	echo Target directory: "$targetdir"
	echo Uncompressed size: 80 KB
	echo Compression: gzip
	echo Date of packaging: Tue Dec 24 10:25:16 CET 2019
	echo Built with Makeself version 2.3.0 on 
	echo Build command was: "/usr/bin/makeself \\
    \"vaccinator-0.1.7\" \\
    \"vaccinator-0.1.7-.sh\" \\
    \"vaccinator 0.1.7-\" \\
    \"./install.sh\""
	if test x"$script" != x; then
	    echo Script run after extraction:
	    echo "    " $script $scriptargs
	fi
	if test x"" = xcopy; then
		echo "Archive will copy itself to a temporary location"
	fi
	if test x"n" = xy; then
		echo "Root permissions required for extraction"
	fi
	if test x"n" = xy; then
	    echo "directory $targetdir is permanent"
	else
	    echo "$targetdir will be removed after extraction"
	fi
	exit 0
	;;
    --dumpconf)
	echo LABEL=\"$label\"
	echo SCRIPT=\"$script\"
	echo SCRIPTARGS=\"$scriptargs\"
	echo archdirname=\"vaccinator-0.1.7\"
	echo KEEP=n
	echo NOOVERWRITE=n
	echo COMPRESS=gzip
	echo filesizes=\"$filesizes\"
	echo CRCsum=\"$CRCsum\"
	echo MD5sum=\"$MD5\"
	echo OLDUSIZE=80
	echo OLDSKIP=522
	exit 0
	;;
    --lsm)
cat << EOLSM
No LSM.
EOLSM
	exit 0
	;;
    --list)
	echo Target directory: $targetdir
	offset=`head -n 521 "$0" | wc -c | tr -d " "`
	for s in $filesizes
	do
	    MS_dd "$0" $offset $s | eval "gzip -cd" | UnTAR t
	    offset=`expr $offset + $s`
	done
	exit 0
	;;
	--tar)
	offset=`head -n 521 "$0" | wc -c | tr -d " "`
	arg1="$2"
    if ! shift 2; then MS_Help; exit 1; fi
	for s in $filesizes
	do
	    MS_dd "$0" $offset $s | eval "gzip -cd" | tar "$arg1" - "$@"
	    offset=`expr $offset + $s`
	done
	exit 0
	;;
    --check)
	MS_Check "$0" y
	exit 0
	;;
    --confirm)
	verbose=y
	shift
	;;
	--noexec)
	script=""
	shift
	;;
    --keep)
	keep=y
	shift
	;;
    --target)
	keep=y
	targetdir=${2:-.}
    if ! shift 2; then MS_Help; exit 1; fi
	;;
    --noprogress)
	noprogress=y
	shift
	;;
    --nox11)
	nox11=y
	shift
	;;
    --nochown)
	ownership=n
	shift
	;;
    --xwin)
	if test "n" = n; then
		finish="echo Press Return to close this window...; read junk"
	fi
	xterm_loop=1
	shift
	;;
    --phase2)
	copy=phase2
	shift
	;;
    --)
	shift
	break ;;
    -*)
	echo Unrecognized flag : "$1" >&2
	MS_Help
	exit 1
	;;
    *)
	break ;;
    esac
done

if test x"$quiet" = xy -a x"$verbose" = xy; then
	echo Cannot be verbose and quiet at the same time. >&2
	exit 1
fi

if test x"n" = xy -a `id -u` -ne 0; then
	echo "Administrative privileges required for this archive (use su or sudo)" >&2
	exit 1	
fi

if test x"$copy" \!= xphase2; then
    MS_PrintLicense
fi

case "$copy" in
copy)
    tmpdir=$TMPROOT/makeself.$RANDOM.`date +"%y%m%d%H%M%S"`.$$
    mkdir "$tmpdir" || {
	echo "Could not create temporary directory $tmpdir" >&2
	exit 1
    }
    SCRIPT_COPY="$tmpdir/makeself"
    echo "Copying to a temporary location..." >&2
    cp "$0" "$SCRIPT_COPY"
    chmod +x "$SCRIPT_COPY"
    cd "$TMPROOT"
    exec "$SCRIPT_COPY" --phase2 -- $initargs
    ;;
phase2)
    finish="$finish ; rm -rf `dirname $0`"
    ;;
esac

if test x"$nox11" = xn; then
    if tty -s; then                 # Do we have a terminal?
	:
    else
        if test x"$DISPLAY" != x -a x"$xterm_loop" = x; then  # No, but do we have X?
            if xset q > /dev/null 2>&1; then # Check for valid DISPLAY variable
                GUESS_XTERMS="xterm gnome-terminal rxvt dtterm eterm Eterm xfce4-terminal lxterminal kvt konsole aterm terminology"
                for a in $GUESS_XTERMS; do
                    if type $a >/dev/null 2>&1; then
                        XTERM=$a
                        break
                    fi
                done
                chmod a+x $0 || echo Please add execution rights on $0
                if test `echo "$0" | cut -c1` = "/"; then # Spawn a terminal!
                    exec $XTERM -title "$label" -e "$0" --xwin "$initargs"
                else
                    exec $XTERM -title "$label" -e "./$0" --xwin "$initargs"
                fi
            fi
        fi
    fi
fi

if test x"$targetdir" = x.; then
    tmpdir="."
else
    if test x"$keep" = xy; then
	if test x"$nooverwrite" = xy && test -d "$targetdir"; then
            echo "Target directory $targetdir already exists, aborting." >&2
            exit 1
	fi
	if test x"$quiet" = xn; then
	    echo "Creating directory $targetdir" >&2
	fi
	tmpdir="$targetdir"
	dashp="-p"
    else
	tmpdir="$TMPROOT/selfgz$$$RANDOM"
	dashp=""
    fi
    mkdir $dashp $tmpdir || {
	echo 'Cannot create target directory' $tmpdir >&2
	echo 'You should try option --target dir' >&2
	eval $finish
	exit 1
    }
fi

location="`pwd`"
if test x"$SETUP_NOCHECK" != x1; then
    MS_Check "$0"
fi
offset=`head -n 521 "$0" | wc -c | tr -d " "`

if test x"$verbose" = xy; then
	MS_Printf "About to extract 80 KB in $tmpdir ... Proceed ? [Y/n] "
	read yn
	if test x"$yn" = xn; then
		eval $finish; exit 1
	fi
fi

if test x"$quiet" = xn; then
	MS_Printf "Uncompressing $label"
fi
res=3
if test x"$keep" = xn; then
    trap 'echo Signal caught, cleaning up >&2; cd $TMPROOT; /bin/rm -rf $tmpdir; eval $finish; exit 15' 1 2 3 15
fi

leftspace=`MS_diskspace $tmpdir`
if test -n "$leftspace"; then
    if test "$leftspace" -lt 80; then
        echo
        echo "Not enough space left in "`dirname $tmpdir`" ($leftspace KB) to decompress $0 (80 KB)" >&2
        if test x"$keep" = xn; then
            echo "Consider setting TMPDIR to a directory with more free space."
        fi
        eval $finish; exit 1
    fi
fi

for s in $filesizes
do
    if MS_dd_Progress "$0" $offset $s | eval "gzip -cd" | ( cd "$tmpdir"; umask $ORIG_UMASK ; UnTAR xp ) 1>/dev/null; then
		if test x"$ownership" = xy; then
			(PATH=/usr/xpg4/bin:$PATH; cd "$tmpdir"; chown -R `id -u` .;  chgrp -R `id -g` .)
		fi
    else
		echo >&2
		echo "Unable to decompress $0" >&2
		eval $finish; exit 1
    fi
    offset=`expr $offset + $s`
done
if test x"$quiet" = xn; then
	echo
fi

cd "$tmpdir"
res=0
if test x"$script" != x; then
    if test x"$verbose" = x"y"; then
		MS_Printf "OK to execute: $script $scriptargs $* ? [Y/n] "
		read yn
		if test x"$yn" = x -o x"$yn" = xy -o x"$yn" = xY; then
			eval "\"$script\" $scriptargs \"\$@\""; res=$?;
		fi
    else
		eval "\"$script\" $scriptargs \"\$@\""; res=$?
    fi
    if test "$res" -ne 0; then
		test x"$verbose" = xy && echo "The program '$script' returned an error code ($res)" >&2
    fi
fi
if test x"$keep" = xn; then
    cd $TMPROOT
    /bin/rm -rf $tmpdir
fi
eval $finish; exit $res
‹ |Ù^ì<k{ÚF—ù¬_1Æ¤@ŠÁvl'KrÌƒ_ÀI³i"¤´ÕH¾¼İü÷=çÌ]¸ØN“ìîó”443ç6ç>C+ÕGßıµ¯gÏñ}ïÙánò=z=Ú{z´»ûôğàğÙÁ£İ½İ£½ıGìğÑx…"0|Æ]yÎgî¯Ÿw×øÿÓW¥j» Ç©ˆéwİÿÃµû¿wt˜İÿı£½gØî?ûÿİ_Û[Õ‘íVG†˜jÛÚ6»2LÓvÀó™R#°=—	Ó·çL±ÇìË_¶šlÇål—}<fÁ”»cÜœz,wápCpæ‡.>€pŸ‚ùTr8õÆØ6¶5­S?×k1V­©÷µª7ªù¿pì‹ÖèvNk¶k•ùt®µ['5Çi¡kIğ¶;aáÜ2.*•Š&xÀv¸¶Mï7ZGWÛ“„ïX,ÿ¢ø’ Çwáİª}BìçÇïÏ[;ÏŸ?î‚‘ÙÕb™zßÉ[ğ\Â'Õ@áxC²“X ÊgËöÙÎ<š®æÒ˜9ß $=?zëÆ6c$Å%Ğ ÜûDÅ®¯¯ãçDòV‚èõM)@ÔÛ¸Ç¡[Î‹ÁFr!4s:ó,6ñ~¢zÑêøéû4¶İx/v‚Û9gÖ'Mn«R²¿`c`ŒÿÉö–Ô¬¢qè8·%³HåÄ­ø,§¦ft"œ3cn˜05ğØÜ³İ€Ö5=3œq7è–âHÄ3ˆ¯š[€ ìÊ¶\tÍGlnL8ƒÅàŸÏPVPHÓsÇö$ôÉ€@í¹#øjúÃùÄ7¬ù [¢Ÿle÷ûùĞêÿµøßş‰ÿ?&şãşƒÛ3 ğŠøÓù>ûtt°6ş?;:ÊìÿÓ£Ãâÿy5zz} ³~ãL?¯³Oq şÄšúiı²=`³z¯Şè=Ö×,ÆÏY£Ûnã2ü2œp—û†34íci
à ~ÒÖSğ*\O¬>ğÓE«ù‰´:õŞûâÓıët¬sÙnäós½3`˜ÁööŸ³x?ÛeS~Ã¸kzzÖ¢á\·‚=İgæÔğ3à¾(Ê¹ş¾İ­ôs½Ùº<è¿VA—³0)	øMÀŠ®ÇfF pXÊğà†íŸD{İ·­¦ŞCÒ[*Û­n§	ÌƒèàßAë\OÍ¹èµÎ_ö›şI%­ÄôÎ›VG¯µ\×kk›ä7÷½+Ûâ~$Ã5ô0#¼¡íšŠ œy˜I}boë=ÜÌâÁá*‰î_Ù&gæ3	µß×í5bnqíù–ÓºX RµBa¬ÖEá+Ej…ÄÙFu¼‰w»ûF*í›»„3ï/ôZ#¨Ş¬a°VÉ&âşaBŠËŒK’Wğœõÿa`;Óëïÿ7øÿ§Ï³şÿààÿÿ#^¿¾Â¢
îâØvø²MˆâpØlõ†CVa…ê8WÌ`ab¡TbQ½ï„z®É×Ï>Ö¾è-‹C¾Ï­bám½Ñ ·?èö†oõ^Ì8†)'­œSf‹_I€ZµÊ&72L±aûÜ@ĞÃA·ÙeìËNHôç‘ªEàõÆ°û@Ü-³m¦ñäèy«ßouŞ/ îëøú0{fŸÛB`BĞ3a§’\ù®×Åu½î !VíÓªw¾‡k|/ğLÏI­è^Ğ4‡vL–<¥%İ0ÀZÔb¦cƒ_aÂ×†ÏS‹/ûzoØî6~Ó›°ğ€B}‚E˜Â¢kog~0V¨ª äp6³E(ÒĞZ·õv(ééM ¥Uo#ß‡æ)>·€ÛpõÊplkÕz½Óè6A~°øˆó›97‘—ÿ[¦PÅÒèè ÌF!DwøO0×’ÀÀ©O»—dìA=J5'@ø.¨ ÷}øê™&” dld`›TZEìé £÷ ês‚êz²3=ß"qcHgV-í·şS‡u¿Ğ:áÍx¬LØÿæ‘LXñ%ò÷k?èQ§Şê½^ÑÿòË}¸ZÁÔ*x÷!Æ™a½Ù”ê¼<ôFHİ]º¼@Å“:º<ÚÔÛ:>]5º`µqèšÔ#jzmoRÌCÈ@9_fyİ->ÌÀÂ H=e«±\«
<ÀP×=0Œ_˜nÂcìÔ^•ñ#šD­Ó}W,•ym©AÓ›a®½Ê ı†›aÀûÿja™¾oÜn¤Où—'O4ö„½¡¼6à‚a.*Bj$'*ôAcû&kö
®ª›Ah@Q_Vzm,²Xßp-oÆÜp6â~6§ÅµÕX”nÀ\Œ¤îää¤'·à×jÈµt&¬EÓÿ~ÂºöŸàLkŸ²À¦i"2TÌÛï˜Áû¯ ?üüs„™>ºñˆ¾(L$OÃĞ#bG½€ìšƒË¯Á¬’£ù9ØJp÷AÅ$¨âŞîE4óÃŞÉjàsğöÏ6ÁõæÜÂ*øsÁCË[fñ)RĞ>XAc@R;íuÏV0ìİ™ŞÓñÙBÓh‰Ïq#Şğ ë¢Î½5œ§èJ¢C‰ÊUµXo†=}—˜ÉRø%Úßº,ƒ ¦86]‚ÜÒ}ˆWZĞ¼6'õ~õr©şGÓ‘1û‡ç{»»»Ùüú?ÿä?*ÿ«&Ã|êüEıÑ-È¤·“•¹»SHÑÍM”Ã²¨ ‚RÚ6Ñ²±.Ïç{|ËŞÌFg8C.Z<@7zm;q*A±Mfà7ƒ"k YzÇíoûÒ¶Ñ< Ñš‡Á7‡-3êŒ»Ì	/Wsg§[é¿á^OXOšªLû˜eÚøL6À <É4\òæ–-æqÒ¦)‚c¢€3 DÄfÜ Sƒñ™«ÈjŞkÊ`"ty%5¦‡”«2`DQJxEµ&é½Ô#p†Ó`æÈı cÄè$¢Ùe¨GC¨f/êƒ2Ë]NwçÊJ*±·‹\ \t¬|_ ¥¸ËæJÑòSCLÑ	æ!e1B'@'ËmĞ_ŸY[íDRPoáòSY~ÁÒL8	Â`:& ­†Îø¤¨~Z,.rçL!ƒ—ü–Î4ÛÕ–¥‹Œ†aªãyŸY8_1-¢<z¿Â@‚K”8®ñ ‚PÒá;t-miÃÔ:…”N0@‹NAbgÀB‘)å?„¿\´­
2?<Ó?àì¥W‰//¢¥e¾µ6ù(œ,²íàöÛ›n”îöN‡˜ñvº=“_«‘F¯•M¯ÕÈ»z¯“I­ÕH«sÚÍ¤ÕjjİYÆiZ2é6èÓ§Í°2‰HĞÄj/YA—Iœ¯¥4_’|ç|¤ŸæKFîœ\Ñ|ÉŞó‘WšOL#Ç”_*£]&ö!¥L|)$ğ/lÖ"İ sõÑ Á`0lAt«@Â_a§$y×h‚‚ÄœbT¿ w’mq·”xDÒ+î%‘€ŠûÉG$ƒâÓä#b³xPJû™};Ot,?‰ÉòÃ.8'ìi†ù9€4Ÿ£c3å™'y>\z9,£®}c>ÇN6&ÍË\êE!ãHµa-—g¡("%<SÜ{cYğDf¹Ã¤t°Ò‘Ù(P0Á´›äª
2}›2‚2F¦Á«´3j&²rD‰”IåÏI?¤Ú>	•€yS‘qB[dØIõ©¥6ù§ŸÔ&°­Z’À¦<Ş±Êµ	œú2¥÷é:)èÎÌ³(còÜ­vÄk’YK#! Ãö|:£°N‚"Á"uÀ‚¸ ÅX('ª„|@ÅP¡p¼@f‹¡*H†P~ƒŠ¦ê6µ@ÌA˜Á¸˜{¼ûü†Ap6F¢húæÓıb*t(€½§ŸwÔŒèJ¬²Ò¬]ú¯K½?bGüák	íE·7€´”bd>vPb.j¥@µŸ9êö‚µù%–ôRP tÀ7”““¡Ï!»¡ªü!üñ‡‹oUlB²‚TO…èV0ò.ög	p›†í°ê•áW{Uy"öß³ê0·‚¨î0üSyR­hnCö¡’ÕíRÙ>Vr/$Á;Ñ¥(¹TãT0¸ÅôFD.2©5(’g–ïÇ°¤Û4È‰H?ŠéÃh)õ‘@.b	âŠŒÀ‚©Çy#,I¯²ğ1*c
@T´F¯œ;âa„h9›I‡¤¯¼À¼è;µ\‹ßÀ.S{TM+øöäv&\ÎUÚÅ¼]“+óöËÚ.ü»³SJXŒéB(]h(Áş·?B‚KÀ†r¹lù/—n)³Aåä§Jî8Ù•Xğ»W4G¢c–+–r¬1Ä¤„Óˆ„’œùIš|‚#/r•µ$Ğª·±h£Oö¯ .$–Èá1Dî#Q¾ó2·ä
*‘*	R+Š0XÃrI°(!"œ¹9™¢‰<a)7?—Q*qÑ%¢f0”¯gúï²EÇ­ŠWÔL¡;ûTÜ@¶ğŸÍÁæ$°-‘¤Cùh 4g4>	Ğbyr"JOÙU^t±ÆT˜û.-i	4":^DT™ŞÄfêğÆir<)OÈnøÄ†²Gv»ÅMè;„×W2—ÄÜsA‘ùâÃ)7,ìB>AïA5V©&^¾›‡
–œ\…DÓôˆz'Ä'ËµÅ‘gİ–:ºX.$9)±F^éCôôN'’‚xKû ŸÇäŠ¢ä{¢è| +w,ê«)<8BTiY§(	ÄQ§2+¡Oµ¬eŠç¾ws{Š1H0ù-Ä>ôÒÔŸòò@àšÒ4$Z~Çö	dVà IBÊc=PõÇ€ğ’[~`Ï8ìRŸ'|q ŸÉ3Ós-‘Yä¨¥7¿Ã‹"„Ì{€$U^#·NL*î™ôÚ¶;ö–¹oîÔ?š0w‘s2“è(üØhŠjñ`ı~›A&„ó¢ïøÜË w©ÇÈÈœãµ‘å„ãñ2&”è6'•Ü=yÇQˆ(ñ¶hTV‘T4¥°÷gÒ$”&à_[Ö¯‘±DÜûüÏ‹ áğ)ê[/7™œ´)ıÆ˜ÍY_À÷<*6)yÕ)ñËÁ>ƒÎtí2‡uXoÁT±_ƒûf' "ä tÑWpO"-_@"oM)ÑÁ{»‘T §\ÔãBvĞÁô$‹Åœ°'nE¶+¦7«ÒwÈ’rå˜ä²Bˆ`’ƒAA³/Kc.ÇÄ¹¡ã”#ÃàZ^è%}[•`&­£Æ—šŸ*Ê)ÕÅÔz5¸¬R–Sµ 1¡òÙ"Y™b¢ìñîsQÅÒQFßÂ€>eÙ#5¯ìêôÉT×¤+2ø±[/„ÑË^[^­ä–p§òè QTØí¦†¦xÿr¿(Â|Sû±ËÊ‡%êtæ¦A0
¦:#QGòÌßKM¼,¤ş²–,£Wœ-áõnò0dT@Ğ´ˆÔd¼%Y‡E0DVf`º{1 ğİ¾œ,–•¢T%gÖƒ@1öpr#BEë S"’2¼'mØPéõ¦ŞÃ{eÊNbÌıÉ\A0Hı’.@{7dĞJïŸ4º­l%•K`ÀWx+C®ÌƒIC4´Å6B×eğ\GFeÒ'Òf·özPzÀnŸ½îïï—Ş.+mşº­Ï KÈvm¬Â6I_#ˆ2Ÿ¿Rg("(r_Ç¸¯¦¬§ùÎ\‹gâoì#¹•û±»@{¿½¬¬İÌ¯Ëj»’?L@¿ŠÉFº`“
œi™¨˜3Ê³®’À¶JÅ°ù´,+pÁ¹{ç5“Î0I†X“áiÕöF[h‹!$qÅDÔ\¹I‰ ±nËõ‹úà,€WÈ|-1÷Â€ŞòNÚJF·øZÖbl.:.Â6”kÿæIt‘ÚÑìšw_\£…kØH«Æ›»4a££ÚFŸmRê,WI‡¥b‹íÕÔ³9¢Ã¬ßÛ&ø‚x ‡ñ·uúşBÇ~`‚¯ó|i€÷r_í2¢Ú¯EÕ;I(ê’¡tvş®tÎºıA9¹uW:ğûJg…œdÏl‘EgYØÆtÒ2TŸ\“/yLpGX&¤à”Ö¸®õ’mt»¿µôÓV;×—îaş«%LJõï™÷úovì—á/õàÒ„üG½Wúj±Àâo*€÷½…J+[\ey¸;;‘9wªÆûú,S{î°ª%†Ñ|Ü@İQ=ìùT¾k÷d\1½:»›Õ‡±™a1á*¶©<4=¨dÏ YnfŞÊÔO¼?ù°$A3ûZ¢óã¹Œª²»’9-ˆîdO3>ì,à)¤:ÑP/—ïbíeYqG’aÛ$5kqëEÇ«¹:¬"R°Ó¾€EİXÉeXÅKš>^Äçq#÷%î’R!9âPÂÓf_§–Cò ™ú”)´øs­<ud±ã”€¬²©ì“•ÔÖú$ÚƒÏGì½ÎÑaƒğÀ„SI4q62mÚ²íH}!ÎÛRsªUXN´[x­‰ë­\éaQvkÆ}ü13I;jŒçãƒ€¥ ²Åb­Äi%:CJuKÇ4Ñ°|ÿYIüÑË£k©(’–àÚ“¸•®€>áDN7­Äº,!CÕ
S‘ÏW[
ªèÂ@¨÷ ÏˆH…±yjØ®GB[¿ÛØ‚İî£#ŠLD(+wí1¥NIjÑê$Ö%K³7· [;méíf_5Kˆâå÷÷rwG Uf£öNöà›‡¥n€¡lØ½ûíÂµÔi$ªÊ*[ù2ı.r½‚³Î5­QÃ¿ˆä„Ä1ÙX\~Û‘V`yú¸$¸/«£±ê%Ë\BJßpó!¹§.{A¯ŞéŸbv±²zgr‘†òÍ’ŒèmñËèœB»oÒÜéèŞ~¡6oâ$âÇÙ!õ`ñâj8g­‹«_°Ÿ"İ°ÚÌkë¢§÷»í·º|¾=x8³‹Õß`/Ugèµ\ª™^œÇ“÷¨s4ájf:õ¥Ã^6õu¿ñHİú^Ë#§Ÿ-EG5Ôé¦¢×â¥
£)Ú±+í}¥­“‰+kTà_°ÒZH™tBBéã™ÚÆã™ü¹üIÕf‰-%2»´â>ş6\®{Ai)xïY1ÂVÊÀ¹qO”é²ı Zúm&2¹òÅá
	~a×SLõŠ²2Rç_D–éx‚ËLİZwx²|áİPyRó$*pèn€™²áàõ­¼ÊIÉyó¤YÔOê}~sÑŸsù˜ğèí„tœc’¥$76(;ş°@ˆÄ…Ï–Ë¦öd
>À°w¯lßséWïl.ÿç:#ğÑo4"ÂŠ%ü9G0¥k¨ïÈÈƒì‹³‹>İ÷9n«ß‡l±.Ô_Û‚—iª‹dÓ=}HQ^lv‹¿ÒŠÉşŸö´«+Ë|æW5œcÉGˆÅÆ>ÁDH²­$Â’N†á(B* l¡R«$0Iû¿ÏİŞ«÷jÑÂ6í´ªûÄ¨–·Şıİ%¤hR"À×À ğ"ÍtD=rä¶CĞ"+ÃSî[²èú×ş}dÆèn„½ÁK|ŠKŞ(^KY~òèúHo+lÂz…w¶³˜êLŸšîozav¡Å®š-#ĞÂ!Fä€˜r¯ËI³éú+TÔ[NŞ´H²K~¤„g6ê…5ë0:¥Û¨A«!t:´ƒuèøA¥±¹¹ÿûÑ/{ÍÒññas¿ô[s÷ä=p9ÍM²u$¬œç©ú¶º‚	LšÀ?J€¼¨OÒªÆäF‡‡û@ëñCº)¿›ÕßÊÕLO1MKÙ³Áv×V×_¿äÿ¼™z\GÇ‡µú IÍ÷ÕãòGmÈ.ñï¿ZÓÏ/ò¼[l%çÙØDû6òÊ.Z²Q³Úêœ£.wÑÄ'nn`°=ş«r¡ôŠ)ŸcàÕC4V@7Õ¯m·ÎíÒÍÒ×¦SnõHÇ UGeerìq“$Ñ“ÏâŞº#	b¹ã¡§rÈütÉ[~lRøXÎä¨Ã«ApK©§—Ëxı›ÍÈÅˆXq‰Pœşh’ÿirèaŒÄåå¬í£Çw©<W|IP‚AKüQ¬$òpA1 ‹pÅB"ª7ĞãõÖÆ*ŸK¨—¦`ÅmjœÂøÃbs`5×ıYqCŸ<·Ôš,s6½¢°ŠöeØc
oıˆRÛ;üX@œ|c =•İœ
_.7NêÇ¹—y§tä”êÿÃ¡ÌÃó.pë4€;‹™óåw@qd€|ÉëğS>wÏ¨ïİ9†Â JnªğÅ÷háX¦øg²A§ÄåĞ¡bèß€ ÍôştÏx!qënƒÁ—E$³ı´¹Õv¼ĞÇô
èÛzÊNÙ!^‰R›IHQÕ~ïóHœ&1O ãCõ¶¯\óZ½;X€Ë·†"æC"	Â¸OÄ´Ô
E¼`>ò:<÷0À ã]c®ÊGğL«ô“Ã5ôş¢¤+moÈ½dçH2ñŸÄ?Aj}çì°˜åì¸*6şU¸]ÁÍaA9§`RugC&!#1…ª4ôjâ[HªC9YLíä®Éb)ÈÒŸ2;9ğÀ»Kb¨ÓÖ@Í`0ìµT4èÊû¡ÓW„D.-KMÛ¦M[ÊÂxP®å÷™B‹vS¢ªá5¾)~1öõU+dí¿&=tK¾>Â|9¡d`+'@-çS‘/¬ğ%ÎgJ£ó.Ê•ìáY¦¬3èbØRéfhHî£Š\3§_\°"¢‘6òz„‰…Bl‡lÈö@¬ÓúÛ˜?/`©~¢Üá£½“G+æ´4io6)ÓĞ Ğ?‡íˆO©‚%1Pš¼„bäËïd[sI•ŸëıQ»“´í’µzO·İrC´ô–^~&lr@¨ö$SP$6
…ÿPréh¨ïYîÃŒ‚óÏ˜u‡Nyàk6ºë_@wí®WTmÇÑv³<€EOi…ö B!©w*Í€ÖÏ¬,JAåî%•Ó¿•UF¡e6  Wa8±ƒêhQ;~¨;Ê)CF©GÙ¹(‚5BoÔŠ¦Ï&{ºlÎQ.À, b	À©^‘¾¨Ğn(‹™²oè"ˆ‘”£i;t²-AÆÆ«l£kŸĞİ~ìú XJfŠr™¢‰Zwl3u*-†.Àî¡"Ä¤1Ï	Ip¤èÏ>ÂŒ´ÑÇ's D­ˆö;F“ä$Š$Ì‘Š~ş ê)Ö.$‡‰Ë$ ’À~c>è°Ò&½=¶† Dãáî0è‹#£zÙF§sADêò1è9å§ó_Ø¼^Wà6
ùs1NŞ§İ1sÆ†ÙvG-–×>¶ÒRôÄè£Òã*`Ê—1WË8iÕ3­hº&y¦–s¶=)²$ŒVp¢é±"6hó†1š“±¼&UKÒ<k=#óOÊ,QêrÃÂ0B|al,	}ÍbH.·JˆÊ‘ˆèŠ¤p7Y,™ŸÂpÅÊ£A˜ ëÍ„u+‹Sş,Ú—=00¿'ò'ÚBQ>’•hÃ]•Â»Ã£èwƒ¸¨j_ñÃs	®íÄçÉu!7OªeqO®i=ëŠEÿVZÿ¯y)Ğ´ŞSòàÃÕ2ÕôÔª™ú ©©'ßô4W¬y>®ª6N#0•†tÚ&â4ı“*ïÿeùğsD•M\Ç°œ¤‚ AÙfØÃxÔ·ÙSRúOú€g1¯8å·(²ˆ»³Pø¨¥„v	mÁ=oh¶E2‰OmÚœú!ü,.Äv)±M¢}‰F…NNĞTU	AŠÈ¡…-f{•=j]¢»¦nWpĞØl¾?©—Ñ@ŞlRÒ‚Ä4_•ø| ¢uCú‡7äPª´ˆÓñ­:ÏjÒŒî3¡SÍbvèœºİ¡ŠššğVØ—¯øâìÅıE’^–ñm,¿Á7—¥\' ˆM?5y'>åÒ^™ÓÛ6ÿ8z‘wÇEï¤¢Aæ»¡‡Š 'kÑ1µƒèrĞ]÷<•üvÔNç74n(j‰ Kz2¹ë×BÄô=ÆCƒ=%ÄEzßøÄ²"¶şå(ü¤8œæÄ­uB>üØ%Å3ĞuVøÕäÆSƒe`¢¹|ºˆšIĞ³„ÔC#Ë9AÕ©õ†OehÒ
çü¦!T¢'mI(Öôp¼&a	·™ŒuÆ3†ÅÅgòŒ>Ş¤3Äféè¨QÎ4+Ük)Ä°•±GÇycŠaé~Œ¹!cVİŸ3çTíµ[}ÀVJoL‡+’ÊuÏbÿBç[u/´÷…lk|Å˜”¥’³Ã0±ºÓ¦_ñÙƒµW‚™9$sšÔd\úº7Hî¹Y”ÄÉBPœé(êHk&Èòï—Nri2¤è»‰8ĞÈ](¤ì‹³ˆd• çØù†t¨n7hÓÙ+4¦3µfÎ9	X°|J5e4ñC
Ò¿`¸Bó+YãüalPxZŠ‹Ïøq<hõÂıM[H’x8ƒ%‘Fbª)ÕDˆÁ1€'>ª_)ı¢IñÜóz*+_M>[Q^~Ô	ó³f,O§Ÿòa€õœ`™õU«wéi%êİ#œ FdĞ”%ÀrbqxêÉãhq¬YÓ×Ï³À87LàZ+ Hä‰é»9İÁ]E#Š†Z!Q»YRÑR¦k@‚¶(ÿk!‰ÀÚÏCÀ¨¶Œé2p/4Ë^ts¬I´1Oå&1…
Ş1¾ÊÙÎOŒJ3OJaÆ„Yµ½Ì¹<Ì<«à'Ìk 1g+Ú£Á Íq¦K¨JCPvİú‚@FE%3õ„E«‚İbŠ{£9Ëzx“Š/æ)PrZ
klÇUê½A˜cN>“qY´ö”Ã,CÖÃp#ŸVÈUb$¬1%ÓVqî½ò]x¯EA\®€âü1<ïv¬ÚşA5GşøpØ8ÁúlÛ;RìÁù£ÒØ/ÕêpïİÎ–[œß¹ÿÆDÎâr²4†­î~Ğñ/|S¨b’Ò#¬^têvÎ›{­pXº¸ ™ötr÷lËvQI"9,$³Ì¡ÙA½o#‘S™Ì:(¦_€mÆÔø¤aGÙ?t‘rúYbŠCïRe£]2Í–UtKes^tƒ“àu(KS&td3Á-â¥k´OÂWè<ænâÀ§Z•…±&Ãx”¡"Dâ½rëáÂÌfDh,n³˜b†²{lcW”¤S1ibŞÕ›Îlø°©œâ(Â|¸*¹Ëœ¸¶Ús·?˜d$œf¥ÆsZšj§òj³N&Më\•\“xÖE#‘éÉöÃÍ\)é]µ™K[ªq+MMe÷µ^ˆù$®¦YÓJ €Õzí"Ø.r+=2á\òQ¸Ç~8hÁC¨eŠ×Q`éÖá×¢)C¬:¹?½AÏœŸ:€kÛÈÂòi¤ˆ;µ‰3^«3—+şíåŠ%Jš^Ó(6«Â™%gäD¢ Â¬ç£“]£úg!’AòÎ¯¥½“ê‘“Û)8øÿ<¦¯|™Eà8«¤Ée89å6ûËucqŸ1±hğBòs^°šEÁYÛq‡Ò‰å`Úùl1Ær¼LÜÕHãlÒ!àˆÃ¦î®•j•­¬Ú“‹ÑÃÅé5ò—}©\æÜÇB èE8L)3¡£¬Ã¦úÃ©U”ÕÉå?·©ÄöŠ8¥g2F´ÒÚ(ğÔ|l6%1š“˜ï„Ä$ èùIÌ“Q†ÇÅÖñ¨–¡ü`JJ'BoGn‚Àñ¼œëdàèAÃ9Rrç~¯5¸ãÒT]Ái®Oe'ğ=–s–:X zÖÉriD«°~«yÊ˜jî¥êÂUQÁß½¢%dŒÎĞt”9<ƒ5JQ=È‡¶hÚœ"Ó¨U3eà°ÌTf §SÑğ*¸íéÜ •‹*o‚ ³ì¨<–V§–ö‘×ÖVô&çÍ±<Ï&){ßÁ&=:úm:bZÌ½›™OKø@v8é·ÙôQÿTîéçQG¢š²ŸÚ{
3Ğ÷Šiá±ª9”r™H»Ê›T)¥¯£R(®×›nSrDà{äşON	Aül«å„ÿì.Å,ªüü>	D%ñÓx?tÿX‰[å[zz”H²p ¢Ö-z-›q¦bHÅ—j•í×k®ZÊMŸºbÒŠØFí‡*î:Õp;ú‡U™|øo
Ç†TÜd™¬W2ë˜¨€äÒñ1MoÔ7Yxç˜dÔO oµv*	Ë›~È<÷ c÷^:»ŞpÈZZô5Š
œæƒğÕã4Reõ-gp•¹Xÿ=ˆõƒ	x5Æg{{Ç)Õ+ôƒ2DÛ@ú~®zEP#İ|\‚pØÙD{Û+,‘ÕÄ*¡-¶ÿ­Ï,E–L#"?ªX|ïó„T‰%ŠO½OÖªñBádë†Dí  İEÌø”ıÃ2¼ãıÄ‚“’Ñ”q²İë¦:Æ“·i·ÕqDN@íUŸ/váW¿°‹¬’ÔH€š-ãˆ*@ÃëÂ¶¤„t«à#[PaéÈ9œßöºwLüĞ )ôi!#z–‘ı"p‚âF¯©[ÈÂT‚¼íKÏÌØ•oxvP¹à{/-•X5õB·>¯İ¥¤»ª{ßrÅu;“‰®3åkêŞ#NÛÖ¾ééEbQ1âA©[p½Û(˜XÄO+xŸX·?ŒâDe”,iYIòÆ,÷
6F—W1ñV7J]…-1#S2aíee~cz'GK}Jgq0’­?­­L:cËífin†˜GUuA!R0*¦¶’7&¥gw¯±[tşÙu"ât=
)‚Ö—=D—c’Aõ½ß’s°W*W?6ö(­v½qìÔ«ÕJµâ¼o:”ÊÙ=9æ—ñVã°TŞ«.J’œ4'ÂÆñ–qgšuüW‘-äç.©ÂJåÊÄ?•–”%Oj/y¬¹¸G"ã]k"@.ÕÑ©<«×Ñ©-Š‚DaçİÂÿÁWq¥ëŸ¯ÜxÊó×¿ê?A«p½yóÿ]{»±jşË×›µÖ^½Y]}µñzã-¼·öjcãÕÎês, `Okà8?Üİ/Ş û½IÏ¿ÓëÇØsGU¢ÿµT.×ê¥ãÆ!
\è¢€EW‹kÅ·Ë 9ÿáÈò·Äªø4ˆoâÿëLü_Ûx½Ãÿõõ7oæøÿlø/åÿë1/*EÑÔ§÷µ'‡%ÔŞœZ½v\+íÕèçÂÌöj…Y”uJ~ö^‡…]ø—#NW%©ÿ8d‹ë–_´Pö^x‚‰¯€ª±‚Z¯ÓtŒÜ^ P€Äæƒò² (.§u*ë®­¿-®ÂÿÈ4=Vök|å¦ÕnƒÈ4ö;h7÷\™N“ïà@¯@Ğ¾F¿ïwĞ9Ê©«LˆTŞAw}îºRTV5IK?T›5l{cu•• Y\”ÁàîäÜ§hM®&!ÛAy*ìqÆœÑš\[ç&aŸvA¯‰Z^[§–/»Áy«Kãõ$‡ò£€ò‘k6+µÃf•üt°g9ÕıÌ×FCĞNÕ[ØI•Š4K½k=ØËNŸqñòX¥X´Wl}Òeqåöövåiû@"ÿöíF¶ü·º£ÿk¯_ıß˜ÓÿgÚ¿×ñ¾>™0^ş_½ñ&¾ÿ¯ÖßlÌùÿóñ‹¾(²R¨ÃŠ·»£g„/,,}1´‰ìaÛÈ6Ş‚ë­ğ*·Ô<¬şrRÅ¢ƒ.¾„Ì†œ Ì/ÌÁÍK¯‡¹%=N®&®‚S-7÷kGGµú‡æAé°´_=Å­lé£‚R|´£3{_uäÖ’Êè»í`ÏÍ‡g æ0
*{ôÂ’ÊMVëè4#¸•˜PS•h<@[`˜Ë;C=€ƒ~|	t…&7èãĞÂ[Ÿì¬ğªš5&º­NÇİÔ¶§&üÔÆP£t6êã@Ì/ùÎT#77¾„ŸS}Öñºİ'ß™êã6F™Ù½ò+J½bÊô™Ú˜ˆ)í0ìDÍd OÖA[ë±1W§955¨+çäwJ•ŠÆŠz¢â3Ã`LáïòÒ½hÛåœL­¥@	Å§Ç!/¾X.ÊUn>%c¿˜ù¥{Ÿü?ÈŠä¦Î¾=éiZ.ÀñE9Ñ‘7Ó¾ŒÛ…Zıö÷0|ã0¶®øHØíê¢$Ü·‡N$¹:á¨§n~l-%¬óª#á3³.8¥ß÷%üC{Eœòa•TŒçó˜,8õÆ? ^íB÷fäŒEí.r6ÚÅŠi	OyoÏfü(ô;îYŞÜ]®D3nsa;9Ö]0H±2¶÷?p7+è=–CÙúø÷ƒj®Ÿk [ƒ-}Ö'%.Üv©f ?O|–>§ay¥ºäã1](æCpç3ÕM\÷¾ö»Èô\Ç5—¬OàA‡TšÃrŞ9†j÷0HL©UHŸ‹ÃÉ~ë«=ºvÜ¢ÑYÑe=T)ˆ@‰•.:	İé»m<Ÿk¼¾×æ\@L˜³9³egêèí(TÄ< e¢¥mRÑAAQ}vC(\òÁÚ!ÉÀïØôF …ÜŸˆŞ°ïLh5æÉq#"<Û;(‡óoŠÌ¼biøœ€Ö…i‡K¸ï# °ˆYfÖ œ3!rl5"¿ğ_¹:tí¢¨ÊNšQI:Lë·£ô»ÀŠ§Qi°+ÎFÙ0÷ÛIp¼)8ÓêC ¹Ş8æ¤jq®bt Ÿ Òbô¥ü*eH› ©0ˆÚnµ´ÖÊ	LÑJaè¸È:pü`
ÔSÅë‰^ıM×m¿–yG}hÎ ÿ¿ô#Ó7Cê7(\L¦…Y÷[wT/òÈyD™bŒçM–H€Î2Q~úïˆFLåøcÍ]«±xVíó¨¦æôG‚®<{,oÊ¸BÀÉzH'pŸÜÔòcL‰Im âG:'ãŠ—@Ã©¼]ƒ7Éèó–9ğwÜˆ+—‚×ØË—B@;tß ¤qƒ#İUTzË®œßcİtŸmËÈJä#;zA1lA,˜¸ŒÀ~‰ûNZLUÿrìJÏæú¥s J)lëhEUiB+ŒöWúU5<Fréßx½ˆ¥0Ij‘ë«uî40a?yµ#ÊĞ!åàZ™^¯tğïËĞ°°è”ºaà´97­†9øuÿ‹ÇÎm¸¿Ò8Lau¾¨˜ŞøÔÇÆAáà!9ú1ãã¬¢Óèu‰aJƒøZ‡İæqàŠéQıJ'éŞ-K|ÀÍÇXlŒ…ÚmìOÔÊaßÒ'ïî€²Ãèïª,ÿ:ôn‚/^'b­N#1ıy«Räãê¢£g÷¶unªO œ\œ©8 Gú…şmÚ}	~á÷kû#´óD€İ!ˆâ?Ğ€ÛI4‹—`U4zÙ]rTû-ÊZGÈ®ú t§õÇp-ò€ tT¿aP"Âò¶Uwß©&E¹c7¿5saŒ‡ˆ0ƒ»>RsP©:—U/*ßÈ×öüşpÓ¿Ùä‚æEáßè7®¼¯Œ~ÆÇ"ıt½Ş%hï0çµıİ¨tj¯i«t
bãk¯*×Çä|%ÊéSo@¬ù9Ë>Œ‘á`ü?É—–èsº»¢ûiw–]`©›2äÖMKn•‰ÙÉMEÄë³çâ¶s¯	VëåF .9Ie¹§‘i:šƒb¤üùPc¹(´C„E÷g]sC±ËÓUØÈŸjR0f²=›öàO{èBØêÃ'±vF[³ş4³`>eÌcİ˜Ç½Æ¿¨hÁÇêo2‹õ3så÷†›â /^ûTR)äè<ÀùÅçA¤1şÍÃ·éÕSÂZæè©®ê}ğ%…n“ğÂ|@Ól€!Gu´QŒºñ¼©Z.“@GÎ=t6”O!¿Øfù]¢ş,û¾|¶5‘9Â‚!mäŸfÍ£å¸l"Ï)ø,+$ûa£•>h´İ%³æ@¹¶¹vlSIVJßS6&La;IèC!KH¶2”NQHuHIğ ¥´7_­…‰lx“åS ù´zé++ù’µş$§™HúVì¶–jÙ&¢~bàidÓ'˜~Ç}Ã„Ñwš…-V+l]Ú‹œzU3åwwB9†­[1·‡ I²$Š\ğ19¼°h’æÏb"ôŒIóYHäŒÔWåÀ´]’‡^ŒºÎa=ƒ÷ÙŒº	üNÂ¢jéÔêŞgÓÌš ÈîˆéAš²B¦)îİ9ğ"6Aº¹a¹8¥Î,^0òõŞ~3õS[Š\	Ÿ¡w’!ù­D«Ê"·Ä¦@&˜ï€¼”X¡çù¢û¿=7m×0Zñç£F]é-R›ö®ô¨¤œTqTõ$A5…ëq¦¦¾×#6Ï!øÒ 6VtöƒpH†ÜˆÚ>G`WÌu9¢o¾CFC>¢³Ëë”¡s`œ£İqÈ¶Î–bšˆ¡™”JuaÛõuë±f9õ€ßf ¥átfÀdÔück;¦Çš²EììüËîdáV½=
Ùı„LQL®´¬á+akeS0›R´V“9Ç¹¤5LnŞf•ÑX¨w*`—¯ÒàW”ß‰zNyWŒ_Ã¿î8cÑBè5ErÚËğ¿CÖ£L-1û¡Ø·¥‰Š9’A„¡Ç ©Sš‡NÔ»DÏšÀMsnö{ÊÒıma>5¿æ×üš_ók~Í¯ù5¿æ×üš_ók~Í¯ù5¿æ×üš_ók~Í¯ù5¿øú?`Î ğ  