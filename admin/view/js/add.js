
function isVisible(el){
	if(typeof $(el).offset() == "undefined") return 0;
	return $('html').scrollTop()+$(window).height()-$(el).offset().top;
}

function getMsg(data){
	data.eType = data.eType || 'err';
	var type = data.eType;
	var showNewMessageDelay = 500;
	if(!$('.events').length){
		$(data.el).find('#item-factory').after('<div class="events">');
	}
	
	$('.events').css('opacity', 0.5).html('<img src="'+root+'admin/view/img/preloader.gif">');
	if(isVisible($('.events')) < 30)
		$('html, body').animate({scrollTop: $(document).scrollTop() + 60}, 0);
	data.eType = 'events ' + data.eType;
	setTimeout(function(){
		$('.events')
			.eq(0)
			.stop()
			.css('opacity', 1)
			.removeClass()
			.addClass(data.eType)
			.html(data.text + (data.path ? ' | <a href="'+data.path+'">Перейти</a>' : ''));
		if(data.delay){
			$('.events').eq(0).stop().css('opacity', 1);
		}
	}, showNewMessageDelay);
}



function checkData(act, data){
	if((act != 'del' && !checkUrl(data.elements['url'])) || !validData){
		return 'Данные введены некорректно';
	}
	//console.log(data);
	if(checkEmpty(data)){
		return 'Не все поля заполнены';
	}
	
	return true;
}


function checkEmpty(data){
	var elInvalid = true;
	data.check.forEach(function(i){//console.log(data.elements, data.elements[i]);
		if(data.elements[i].value == '') {
			elInvalid = false;
			return false;
		}
	});
	return !elInvalid;
}

function checkUrl(url){
	return !(url && url.value.match('[A-Za-zа-яА-ЯЁё0-9-]{1,50}')) ? false : true;
}


function send(act, data, self){
	//console.log(self[0]);return;
	var fd = new FormData(self[0]);
	if(filesUpload.length)
		filesUpload.forEach(function(i){
			fd.append('files[]', i);
		});
	//fd.delete('morephotos');
	//console.log(fd);
	
	var type = root + 'admin/'+data.type+'/';
	
	$.ajax({
		url : type+act+'/',
		type: "POST",
		processData: false,
		contentType: false,
		data: fd, 
		dataType : 'json',
		success: function(data){//$('body').prepend(data);
			if(data.code == 10){
				document.location.href = type + 'edit/' + data.id + '/';
				return;
			}
			if(data.code){
				getMsg({text:'Действие выполнено успешно', eType:'success', el:self, delay: 15, path:data.link});
				if(act != 'edit'){
					$('#morephotosContainer').html('');
				}
				else if(act == 'del'){
					$(self).find(" :selected").remove();
				}
				filesUpload = [];
			}else{
				console.log(data);
				getMsg({text: (typeof data.msg != "undefined" ? data.msg : 'Ошибка'), eType:'err', el:self, delay: 10});
			}
		}
	});
}



//data: {type, elements, check}
function item(act, data){
	var valid;
	var self = $('#'+act+'-'+data.type);
	
	if(typeof(tinymce) != "undefined" && data.elements['content']/*data.type != 'page' && data.type != 'news'*/&& $('textarea#simple-editor')) 
		data.elements['content'].value = $('#editors span.active').attr('id') == 'visual' ? tinymce.get('content').getContent() : $('textarea#simple-editor').val();
	
	/*try{
		if(data.type == 'news') 
			data.elements['content'].value = tinymce.get('content').getContent();
	}catch(e){}*/
	
	
	if((valid = checkData(act, data)) !== true){
		getMsg({text:valid, eType:'err', el:self, delay: 5});
		return false;
	}
	
	send(act, data, self);
}




/*удалить*/
function delItem(el, slug, id, type){
	var type = type || 'post';
	$.post(root + 'admin/'+slug+'/del/'+type+'/'+id+'/', function(data){
		if(data.substr(0, 2) == 'OK'){
			alert('Действие выполнено успешно');
			$(el).closest('tr').remove();
		}else{
			alert('Ошибка: '+data);
		}
	});
}

function checkElemets(type, act){
	console.log(type);
	var checkElemets = {
		categories:{
			add:['name','url', 'uploadimgname', 'parent'],
			edit:['name','url', 'uploadimgname'],
		},
		products:{
			add:['name','url', 'cat_id', 'uploadimgname'],
			edit:['name','url', 'uploadimgname','price','descr'],
		},
		pages:{
			add:['content','title'],
			edit:['content','title'],
		},
		educators:{
			add:[],
			edit:[],
		},
		news:{
			add:[],
			edit:[],
		},
	}
	//console.log(type, act);
	
	return checkElemets[type][act];
}

//----------------------------
function addTerm(type){
	var termNameInput = $('#post-' + type).find('input#new-' + type);
	var newTerm = termNameInput.val();
	if(!newTerm) return;
	var existingTerms = $('#' + type + ' > input[type="checkbox"]');
	
	if(issetTerm(existingTerms, newTerm)){
		termNameInput.val('');
		return;
	}
	
	$.post(root + 'admin/' + postSlug + '/add-term/' ,{type: type, name: newTerm, async: 1}, function(data){
		if(data)
			$('#post-' + type).find('#' + type).append('<input type="checkbox" value="'+newTerm+'" /> '+newTerm + '<br>');
		else
			termNameInput.val('');
	});
}

function issetTerm(existingTerms, newTerm){
	var isset = false;
	
	$(existingTerms).each(function(i, item){
		if($(item).val() == newTerm){
			isset = true;
			return;
		}
	});
	
	return isset;
}
//----------------------------	
	


var validData = true;
var filesUpload = [];
var content = '';
$(function(){
	
	$('form input#item-factory').click(function(){
		var form = $(this).closest('form')[0];
		var [act, type] = $(form).attr('id').split('-');
		
		item(act, {
			type:type, 
			elements:form.elements,
			check:checkElemets(type, act)
		})
	});
	
	
	/*common checking inputs*/
	
	$('#title').on('keyup', function(e){
		validData = translate1(this.id, 'url');
		$('#url').val($('#url').val().replace(/\(|\)/ig, ''));
		$('#url').val($('#url').val().replace(/[^-a-z0-9]+/ig, '-'));
	});
	
	$('#title').on('blur', function(e){
		$('#url').val($('#url').val().replace(/\(|\)/ig, ''));
		$('#url').val($('#url').val().replace(/[^-a-z0-9]+/ig, '-'));
	});
	
	$('#price').on('blur', function(){
		validData = $.isNumeric($(this).val());
		$(this).css('background',validData ? 'lightgreen' : 'red');
	});
	
	
	
	
	if(!localStorage.getItem("visual-editor"))
		localStorage.setItem("visual-editor", "2");
	
	$('#editors').prepend('<div class="right choose-editor"><span id="visual">Визуальный</span> | <span id="simple">Текстовый</span></div>');
	
	$('#editors > textarea').after('<textarea id="simple-editor" style="width:100%;height: 600px;display: none;"></textarea>');
	
	if(localStorage.getItem("visual-editor") == "2"){
		setTimeout(function(){
			$('.mce-tinymce').css('display', 'none');
			$('#editors > textarea#simple-editor').html(text).css('display', 'block');
		$('#editors > .choose-editor > #simple').addClass('active');
		}, 500);
	}else{
		setTimeout(function(){
			tinymce.get('content').setContent(text);
			$('.mce-tinymce').css({'visibility': 'visible', 'display': 'block'});
			$('#editors > .choose-editor > #visual').addClass('active');
		}, 1000);
		
	}
	
	$('.choose-editor').click(function(e){
		if($(e.target).hasClass('active')) return;
		console.log(e.target.id, e.target)
		
		$(this).children('span').removeClass('active');
		$(e.target).addClass('active');
		
		if(e.target.id == 'visual'){
			localStorage.setItem("visual-editor", "1");
			$('.mce-tinymce').css('display', 'block');
			tinymce.get('content').setContent(content = $('textarea#simple-editor').css('display', 'none').val());
		}else{
			localStorage.setItem("visual-editor", "2");
			$('.mce-tinymce').css('display', 'none');
			$('textarea#simple-editor').css('display', 'block').val(content = tinymce.get('content').getContent());
		}
	});
	
	
	
	
});