require.config({
	baseUrl: 'resource/js/app',
	paths: {
		'jquery': '../lib/jquery-1.11.1.min',
		'jquery.ui': '../lib/jquery-ui-1.10.3.min',
		'jquery.caret': '../lib/jquery.caret',
		'jquery.jplayer': '../../components/jplayer/jquery.jplayer.min',
		'jquery.zclip': '../../components/zclip/jquery.zclip.min',
        'sweetalert': '../../components/sweetalert/sweet-alert.min',
		'bootstrap': '../lib/bootstrap.min',
		'bootstrap.switch': '../../components/switch/bootstrap-switch.min',
        'bootstrap.Validator': '../../components/bootstrapValidator/bootstrapValidator.min',
        'bootbox': '../../components/bootbox/bootbox.min',
		'angular': '../lib/angular.min',
		'angular.sanitize': '../lib/angular-sanitize.min',
		'underscore': '../lib/underscore-min',
		'chart': '../lib/chart.min',
		'moment': '../lib/moment',
		'filestyle': '../lib/bootstrap-filestyle.min',
		'datetimepicker': '../../components/datetimepicker/bootstrap-datetimepicker.min',
		'daterangepicker': '../../components/daterangepicker/daterangepicker',
		'colorpicker': '../../components/colorpicker/spectrum',
		'editor': '../../components/tinymce/tinymce.min',
		'css': '../lib/css.min'
	},
	shim:{
		'jquery.ui': {
			exports: "$",
			deps: ['jquery']
		},
		'jquery.caret': {
			exports: "$",
			deps: ['jquery']
		},
		'jquery.jplayer': {
			exports: "$",
			deps: ['jquery']
		},
        'sweetalert': {
            deps: ['css!../../components/sweetalert/sweet-alert.css']
        },
		'bootstrap': {
			exports: "$",
			deps: ['jquery']
		},
		'bootstrap.switch': {
			exports: "$",
			deps: ['bootstrap', 'css!../../components/switch/bootstrap-switch.min.css']
		},
        'bootbox': {
            exports: "b",
            deps: ['bootstrap']
        },
        'bootstrap.Validator': {
            exports: "$",
            deps: ['bootstrap', 'css!../../components/bootstrapValidator/bootstrapValidator.min.css']
        },
		'angular': {
			exports: 'angular',
			deps: ['jquery']
		},
		'angular.sanitize': {
			exports: 'angular',
			deps: ['angular']
		},
		'emotion': {
			deps: ['jquery']
		},
		'chart': {
			exports: 'Chart'
		},
		'filestyle': {
			exports: '$',
			deps: ['bootstrap']
		},
		'datetimepicker': {
			exports: '$',
			deps: ['bootstrap', 'css!../../components/datetimepicker/bootstrap-datetimepicker.min.css']
		},
		'daterangepicker': {
			exports: '$',
			deps: ['bootstrap', 'moment', 'css!../../components/daterangepicker/daterangepicker.css']
		},
		'colorpicker': {
			exports: '$',
			deps: ['css!../../components/colorpicker/spectrum.css']
		},
		'map': {
			exports: 'BMap'
		}
	}
});