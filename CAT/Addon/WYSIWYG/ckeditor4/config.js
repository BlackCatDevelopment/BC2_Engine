CKEDITOR.editorConfig = function( config ) {
    config.entities = false;
    config.filebrowserImageBrowseUrl = CAT_URL + '/backend/media/index';
    //'javascript:void(0)'; // just make the button appear
    
/*
config.filebrowserUploadUrl = CAT_URL+'/modules/wysiwyg/filemanager/browser/default/connectors/php/upload.php?Type=File';
    config.filebrowserImageBrowseUrl = CAT_URL+'/modules/wysiwyg/filemanager/browser/default/browser.html?Type=Image&amp;Connector='+CAT_URL+'/modules/wysiwyg/filemanager/browser/default/connectors/php/connector.php';
    ,filebrowserImageBrowseUrl : '{\$CAT_URL}/modules/ckeditor4/ckeditor/filemanager/fck/browser/default/browser.html?Type=Image&amp;Connector={\$CAT_URL}/modules/ckeditor4/ckeditor/filemanager/fck/browser/default/connectors/php/connector.php'
    ,filebrowserFlashBrowseUrl : '{\$CAT_URL}/modules/ckeditor4/ckeditor/filemanager/fck/browser/default/browser.html?Type=Flash&amp;Connector={\$CAT_URL}/modules/ckeditor4/ckeditor/filemanager/fck/browser/default/connectors/php/connector.php'
	,filebrowserImageUploadUrl : '{\$CAT_URL}/modules/ckeditor4/ckeditor/filemanager/fck/browser/default/connectors/php/upload.php?Type=Image'
    ,filebrowserFlashUploadUrl : '{\$CAT_URL}/modules/ckeditor4/ckeditor/filemanager/fck/browser/default/connectors/php/upload.php?Type=Flash'
*/
};

    