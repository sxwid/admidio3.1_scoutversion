Diese Plugin erlaubt eine vom Webmaster editierbare History im Admidiostyle, abgespeichert in der Datenbank

Dafür muss html Text erlaubt werden und zwar muss die Funktion admStrStripTagsSpecial
in der Datei adm_program/system/string.php um folgendes ergänzt werden:
        && $key != 'hist_description' //ptabaden change

in der datei 
adm_program/system/ckeditor_upload_handler.php:
        // PTABADEN CHANGE
        case 'hist_description':
            $folderName = 'history';
            break;