(function($) {
    if(typeof window.CKEDITOR_BASEPATH == "undefined") {
        window.CKEDITOR_BASEPATH = CAT_URL + "/CAT/vendor/ckeditor/ckeditor/";
        $.getScript(CAT_URL+"/CAT/vendor/ckeditor/ckeditor/ckeditor.js", function(data,textStatus,jqxhr) {
            CKEDITOR.config.customConfig = CAT_URL+"/CAT/Addon/WYSIWYG/CKEditor4/config.js";
            CKEDITOR.plugins.basePath = window.CKEDITOR_BASEPATH+'plugins/';
            // add Filemanager
            CKEDITOR.on('dialogDefinition', function(ev) {
                var dialogName = ev.data.name;
                var dialogDefinition = ev.data.definition;
                var dialog = ev.data.definition.dialog;
                if (dialogName == 'image') { //dialogName is name of dialog and identify which dialog is fired.
                    var infoTab = dialogDefinition.getContents('info'); // get tab of the dialog
                    var browse = infoTab.get('browse'); //get browse server button
                    browse.onClick = function() {
                        $.ajax({
                            type    : 'POST',
                            url     : CAT_ADMIN_URL+'/media',
                            dataType: 'json',
                            data    : { type: "image", ashtml: true },
                            success : function(data, status) {
                                if($('#bsDialog').length) {
                                    $('#bsDialog .modal-dialog').addClass('modal-xl');
                                    $('#bsDialog .modal-title').html($.cattranslate('Choose an image'));
                                    $('#bsDialog .modal-body').html(data.message);
                                    $('#bsDialog .modal-body a[href]').unbind('click').bind('click', function(e) {
                                        e.preventDefault();
                                        dialog.selectPage('info');
                                        var tUrl = dialog.getContentElement('info', 'txtUrl');
                                        tUrl.setValue($(this).attr("href"));
                                        $('#bsDialog').modal('hide');
                                        $('#bsDialog .modal-dialog').removeClass('modal-xl');
                                    });
                                    $('#bsDialog').css('z-index','11111');
                                    $('#bsDialog').modal('show');
                                }
                            }
                        });
                    };
                }
            });
/*
var dialog = CKEDITOR.dialog.getCurrent();

dialog.selectPage('info');

var tUrl = dialog.getContentElement('info', 'txtUrl');

tUrl.setValue("put value of image path");
*/

            $("textarea.wysiwyg").each(function() {
                CKEDITOR.replace($(this).attr("id"))
                    .on('change', function( evt ) {
                        evt.editor.updateElement();
                    });
            });

            var makeCRCTable = function(){
                var c;
                var crcTable = [];
                for(var n =0; n < 256; n++){
                    c = n;
                    for(var k =0; k < 8; k++){
                        c = ((c&1) ? (0xEDB88320 ^ (c >>> 1)) : (c >>> 1));
                    }
                    crcTable[n] = c;
                }
                return crcTable;
            }

            var crc32 = function(str) {
                var crcTable = window.crcTable || (window.crcTable = makeCRCTable());
                var crc = 0 ^ (-1);
                for (var i = 0; i < str.length; i++ ) {
                    crc = (crc >>> 8) ^ crcTable[(crc ^ str.charCodeAt(i)) & 0xFF];
                }
                return (crc ^ (-1)) >>> 0;
            };

            $('body').on('focus', '[contenteditable]', function(evt) {
                const $this = $(this);
                $this.data("before", crc32($this.html()));
                return $this;
            }).on('blur', '[contenteditable]', function(e) {
                const $this = $(this);
                if ($this.data('before') !== crc32($this.html())) {
                    $this.data('haschanged','1');
                    $this.addClass('haschanged');
                }
            });
        });
    }
})(jQuery);


