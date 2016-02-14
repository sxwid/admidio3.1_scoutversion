Diese Plugin erlaubt eine vom Webmaster editierbare Startseite im Admidiostyle, abgespeichert in der Datenbank

Dafür muss html Text erlaubt werden und zwar muss die Funktion admStrStripTagsSpecial
in der Datei adm_program/system/string.php um folgendes ergänzt werden:
        && $key != 'sts_description' //ptabaden change

in der datei 
adm_program/system/ckeditor_upload_handler.php:
        // PTABADEN CHANGE
        case 'sts_description':
            $folderName = 'sts';
            break;