#!/bin/bash

common_words="admin|Admin|.css|DAG|DAGs|dropdown|.jpg|recurringly|REDCap|REDCap's|subtab"
code_words="<?php|AdminConfig|APP_PATH_DOCROOT|autoload|checkAdminPagePermission|codeCoverageIgnore|DataTransfer"
code_words+="|__DIR__|datetime|getAdminConfig|getMessage|getUrl|ob_get_clean|ob_start|IU\\\DataTransfer"
code_words+="|ProjectGeneral|renderProjectPageContentHeader|renderAdminPageContentHeader|str_replace"

echo "README.md:"
echo "------------------------------"
# spell README.md --dictionary=dictionary.txt | sed -E 's/(config|jpg)//g' | sort | uniq
# cat README.md | spell | sort | uniq
words="config-management|@CHARLIMIT|@NOMISSING|@WORDLIMIT|SPDX"
words+="|${common_words}"
cat README.md | sed -E "s/($words)//g" | ispell -l -o | sort | uniq



echo ""
echo "classes/Help.php:"
echo "------------------------------"
words='Api|getHelp|getHelpWithPageLink|getTitle|getTopics|isValid|isValidTopic'
words+='|CONFIG|Sql|ucwords'
words+="|${common_words}|${code_words}"
cat classes/Help.php  | sed -E "s/($words)//g" | ispell -l -H | sort | uniq

echo ""
echo "web/user_manual.php:"
echo "------------------------------"
words='config-creation.jpg|configCreationImage|cssFile|getUrl|@NOMISSING|selfUrl'
words+="|${common_words}|${code_words}"
cat web/user_manual.php  | sed -E "s/($words)//g" | ispell -l -H | sort | uniq

echo ""
echo "web/admin/info.php:"
echo "------------------------------"
words='ADMIN_|Config|configUrl|cronDetailUrl|selfUrl'
words+="|${common_words}|${code_words}"
cat web/admin/info.php  | sed -E "s/($words)//g" | ispell -l -H | sort | uniq

echo ""
