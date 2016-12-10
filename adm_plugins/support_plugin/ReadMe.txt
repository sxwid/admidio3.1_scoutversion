Diese Plugin erlaubt eine vom Webmaster editierbare Zusatzseite im Admidiostyle, abgespeichert in der Datenbank

Dafür muss html Text erlaubt werden und zwar muss die Funktion admStrStripTagsSpecial
in der Datei adm_program/system/string.php um folgendes ergänzt werden:
        && $key != 'support_description' //ptabaden change

in der datei 
adm_program/system/ckeditor_upload_handler.php:
        // PTABADEN CHANGE
        case 'support_description':
            $folderName = 'support';
            break;

Benötigt ein Datenbankfeld mit dem Namen: 

pta_user_support und den beiden Columns: support_id (int) und support_description (text). Primary Key auf support_id. 
