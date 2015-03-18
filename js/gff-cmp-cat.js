$(document).ready(function()
{
	var pid = Sha256.hash(Math.random());
	
	$("#div-run-statistics").statistics({
		StatisticsCallback: function (upload_data) {
			//alert(JSON.stringify(data));
			$(this).css({"pointer-events": "none", "background-color": "#bfbfbf"}).after('<div id="div-processing" style="float:right"><img src="../img/processing.gif" />Processing...</div>');
			$(".ajax-file-upload-red").css({"pointer-events": "none", "background-color": "#bfbfbf"});
			
			obj_ckbox = $("input.cmp-ckbox:checked");
			var ckbox_val = [];
			for(var i=0; i<obj_ckbox.length; i++) {
				ckbox_val[i] = obj_ckbox.eq(i).val();
			}
			var now_time = new Date().getTime()
			$.extend(upload_data, {ckbox: ckbox_val, timestamp: now_time});
			
			$.ajax({
				type: "POST",
				url: "../php/gff-cmp-cat.php?pid="+pid,
				data: upload_data,
				success: function (resp,textStatus, jqXHR) {
							 //Show Message	
							 //$("#statistical-result").html($("#statistical-result").html()+resp);
							 var re = new createResultDiv(now_time);
							 re.result.html(resp);
							 re.button_download.appendTo(re.button_frame);
							 $("#tabs-4").append(re.main_frame);
							 
							 $("#div-processing").remove();
							 $("#button-run-statistics").css({"pointer-events": "auto", "background-color": "#77b55a"});
							 $(".ajax-file-upload-red").css({"pointer-events": "auto", "background-color": "#e4685d"});
							 $("#tabs").tabs("option", "active", 3);
							 //alert(resp);
						 },
				error: function(jqXHR, textStatus, errorThrown) {
							$("#div-processing").remove();
							$("#button-run-statistics").css({"pointer-events": "auto", "background-color": "#77b55a"});
							$(".ajax-file-upload-red").css({"pointer-events": "auto", "background-color": "#e4685d"});
							
							alert("Error status: "+textStatus+"\nError thrown: "+errorThrown);
					   },
			});
		}
	});

	$( "#tabs" ).tabs({
		active: 1 // To show the Uploading panel when initialization.
	});

	$("#fileuploader1").uploadFile({
		url:"../php/upload.php?pid="+pid,
		multiple:true,
		maxFileCount: 2,
		allowedTypes: "gff,gff3",
		fileName:"myfile",
		dragDropStr: "<span><b>or Drag &amp; Drop Files</b></span>",
		doneStr:"Check GFF", 
		checkCallback: function (upload_data) {
			$(this).css({"pointer-events": "none", "background-color": "#bfbfbf"}).after('<div id="div-processing" style="float:right"><img src="../img/processing.gif" />Processing...</div>');
			$(this).parent("div").find(".ajax-file-upload-red").last().css({"pointer-events": "none", "background-color": "#bfbfbf"});
			
			var that = $(this);
			obj_ckbox = $("input.qc-ckbox:checked");
			var ckbox_val = [];
			for(var i=0; i<obj_ckbox.length; i++) {
				ckbox_val[i] = obj_ckbox.eq(i).val();
			}
			var now_time = new Date().getTime()
			
			$.ajax({
				type: "POST",
				url: "../php/qc.php?pid="+pid,
				data: {gff: upload_data, ckbox: ckbox_val, timestamp: now_time},
				success: function (resp,textStatus, jqXHR) {
							 //Show Message	
							 //$("#checking-result").html($("#checking-result").html()+resp);
							 var re = new createResultDiv(now_time);
							 re.result.html(resp);
							 $("#tabs-3").append(re.main_frame);
							 
							 //$("#checking-result").html(resp);
							 $("#div-processing").remove();
							 that.css({"pointer-events": "auto", "background-color": "#77b55a"});
							 that.parent("div").find(".ajax-file-upload-red").last().css({"pointer-events": "auto", "background-color": "#e4685d"});
							 $("#tabs").tabs("option", "active", 2);
							 //alert(resp);
						 },
				error: function(jqXHR, textStatus, errorThrown) {
							$("#div-processing").remove();
							that.css({"pointer-events": "auto", "background-color": "#77b55a"});
							that.parent("div").find(".ajax-file-upload-red").last().css({"pointer-events": "auto", "background-color": "#e4685d"});

							alert("Error status: "+textStatus+"\nError thrown: "+errorThrown);
					   },
			});
		
		},
		
		deleteCallback: function (data, pd) {
		$.post("../php/delete.php?pid="+pid, {op: "delete",name: data},
		function (resp,textStatus, jqXHR) {
			//Show Message
			//alert(resp);
		});
			 
		$("#div-run-statistics").statistics('SetNumFile', $(".ajax-file-upload-filename").length);
		$("#div-run-statistics").statistics('DelRadios', data.replace(/\["(.+)"\]/g, '$1'));
		},

		onSubmit:function(files)
		{
			//$("#eventsmessage").html($("#eventsmessage").html()+"<br/>Submitting:"+JSON.stringify(files));
		},
		onSuccess:function(files,data,xhr,pd)
		{
			$("#div-run-statistics").statistics('SetRadios', files);
			
			//$("#eventsmessage").html($("#eventsmessage").html()+"<br/>Success for: "+JSON.stringify(data));		
		},
		afterUploadAll:function()
		{
			//$("#num_files").val($(".ajax-file-upload-filename").length).change(); //Getting the numbers of files
			$("#div-run-statistics").statistics('SetNumFile', $(".ajax-file-upload-filename").length);
			
			//$("#eventsmessage").html($("#eventsmessage").html()+"<br/>All files are uploaded");
		},
		onError: function(files,status,errMsg)
		{
			//$("#eventsmessage").html($("#eventsmessage").html()+"<br/>Error for: "+JSON.stringify(files));
		}
	});
	
	$("#qc-ckbox-all").change(function () {
		var is_checked = $(this).is(":checked");
		$(".qc-ckbox").each(function() {
			$(this).prop("checked", is_checked).attr("checked", is_checked);
		});
	});
	
	$("#cmp-ckbox-all").change(function () {
		var is_checked = $(this).is(":checked");
		$(".cmp-ckbox").each(function() {
			$(this).prop("checked", is_checked).attr("checked", is_checked);
		});
	});
	
	function createResultDiv(now_time) {
		this.main_frame = $('<div class="div-result-framework" id="div-result-framework-'+now_time+'"></div>');
		this.button_frame = $('<div class="div-button-framework" id="div-button-framework-'+now_time+'"></div>').appendTo(this.main_frame);
		this.button_close = $('<div class="div-button-close" id="div-button-close-'+now_time+'"><span class="ui-icon ui-icon-closethick"></span></div>').appendTo(this.main_frame);
		this.result = $('<div class="div-result" id="div-result-'+now_time+'"></div>').appendTo(this.main_frame);
		this.button_download = $('<div class="div-button-green" id="button-download-'+now_time+'">Detailed results download</div>');
		
		var obj = this;		
		this.button_close.click(function() {
			if(confirm("You are about to close the result window. Are you sure to continue?"))
			{
				$(obj.main_frame).remove();
			}
		});
		
		this.button_download.click(function(){
			location.href= '../php/download.php?pid='+pid+'&filename='+now_time;
		});
	
		return this;
	}
});

(function($) {

	var btn = $("#button-run-statistics");
	var origin_radio1 = $('<div class="css-td"><input type="radio" class="rad-gff-file" id="origin_radio1" name="gff_origin" checked="checked" /></div>');
	var curated_radio1 = $('<div class="css-td"><input type="radio" class="rad-gff-file" id="curated_radio1" name="gff_curated" /></div>');
	var origin_radio2 = $('<div class="css-td"><input type="radio" class="rad-gff-file" id="origin_radio2" name="gff_origin" /></div>');
	var curated_radio2 = $('<div class="css-td"><input type="radio" class="rad-gff-file" id="curated_radio2" name="gff_curated" checked="checked" /></div>');

	var header_div = $('<div class="css-th"><div class="css-td">File name</div><div class="css-td">Original model</div><div class="css-td">Curated file</div></div>');
	var first_file_div = $('<div class="css-tr"><div class="div-upload-file-name css-td"></div></div>').append(origin_radio1).append(curated_radio1);
	var second_file_div = $('<div class="css-tr"><div class="div-upload-file-name css-td"></div></div>').append(origin_radio2).append(curated_radio2);
	$('#radio-run-statistics').append(header_div);
	$('#radio-run-statistics').append(first_file_div);
	$('#radio-run-statistics').append(second_file_div);
	
	obj_rad_gff_file = $(".rad-gff-file");
	obj_label = $(".div-upload-file-name");
	
	obj_rad_gff_file.change(function() {
		/*------------------------------------------------------
		obj_rad_gff_file.eq(0).attr("id") = origin_radio1
		obj_rad_gff_file.eq(1).attr("id") = curated_radio1
		obj_rad_gff_file.eq(2).attr("id") = origin_radio2
		obj_rad_gff_file.eq(3).attr("id") = curated_radio2
		When origin_radio1 is clicked, the curated_radio2 would also be checked, vice versa. (Let X=index(0), Y=index(3))
		Likewise, origin_radio2 is clicked, the curated_radio1 would also be checked, vice versa. (Let X=index(1), Y=index(2))
		It can be summarized as the equation: Y = -X + 3
		------------------------------------------------------*/
		var current_inx = obj_rad_gff_file.index(this);
		var corresponding_idx = (-1)*current_inx+3;
		obj_rad_gff_file.removeAttr("checked");
		obj_rad_gff_file.eq(current_inx).prop("checked", true).attr("checked", true);
		obj_rad_gff_file.eq(corresponding_idx).prop("checked", true).attr("checked", true);
		
		post_files[obj_rad_gff_file.eq(current_inx).attr("name")] = obj_rad_gff_file.eq(current_inx).attr("value");
		post_files[obj_rad_gff_file.eq(corresponding_idx).attr("name")] = obj_rad_gff_file.eq(corresponding_idx).attr("value");

	});
	
	var upload_files = [];
	var opts = {};
	var post_files = {};
	
	var methods = {
		init : function(options) {
			var s = $.extend({
				StatisticsCallback: false
			}, options);
			
			opts = s;
			btn.click(function () {
				opts.StatisticsCallback.call(this, post_files);
			});
		},
		
		SetNumFile : function(NumFile) {
			if(NumFile == 2){
				//setRadio();
				this.attr('style', "display:block;");
								
			}
			else {
				//emptyRadio();
				this.attr('style', "display:none;");
			}
		},
		
		SetRadios : function(FileName) {
			upload_files.push(FileName);
			if(upload_files.length == 2) {
				obj_rad_gff_file.eq(0).attr("value", upload_files[0]);
				//obj_label.eq(0).html(upload_files[0]);
				obj_rad_gff_file.eq(1).attr("value", upload_files[0]);
				//obj_label.eq(2).html(upload_files[0]);
				obj_label.eq(0).text(upload_files[0]);
				obj_rad_gff_file.eq(2).attr("value", upload_files[1]);
				//obj_label.eq(1).html(upload_files[1]);
				obj_rad_gff_file.eq(3).attr("value", upload_files[1]);
				//obj_label.eq(3).html(upload_files[1]);
				obj_label.eq(1).text(upload_files[1]);
				
				obj_rad_gff_file.eq(0).prop("checked", true).attr("checked", true);
				obj_rad_gff_file.eq(3).prop("checked", true).attr("checked", true);
				post_files[obj_rad_gff_file.eq(0).attr("name")] = obj_rad_gff_file.eq(0).attr("value");
				post_files[obj_rad_gff_file.eq(3).attr("name")] = obj_rad_gff_file.eq(3).attr("value");
			}
		},
		
		DelRadios : function(FileName) {
			$(".rad-gff-file").each(function() {
				if($(this).val() == FileName) {
					var rad_id = $(this).attr("id");
					$(this).removeAttr("value");
					$("label[for='"+rad_id+"']").html("");
				}
			});
			
			for(var i=0; i<upload_files.length; i++) {
				if(upload_files[i] == FileName) {
					upload_files.splice(i,1);
				}
			}
		}
		
	};	
	
	
	$.fn.statistics = function(method) {
		// Method calling logic
		if (methods[method]) {
			return methods[method].apply( this, Array.prototype.slice.call(arguments, 1));
		} else if (typeof method === 'object' || ! method) {
			return methods.init.apply(this, arguments);
		} else {
			//$.error( 'Method ' +  method + ' does not exist on jQuery.tooltip' );
		}   
	};

})(jQuery);
