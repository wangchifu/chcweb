/**
 * @license Copyright (c) 2003-2019, CKSource - Frederico Knabben. All rights reserved.
 * For licensing, see https://ckeditor.com/legal/ckeditor-oss-license
 */

CKEDITOR.editorConfig = function( config ) {
	// Define changes to default configuration here. For example:
	// config.language = 'fr';
	// config.uiColor = '#AADC6E';
    config.language = 'zh';
    config.allowedContent = true;
    config.filebrowserUploadUrl = '../../laravel-filemanager/upload?type=Files';
    config.extraPlugins = 'textmatch,autolink';
};
