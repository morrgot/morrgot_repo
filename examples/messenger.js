// Messenger is the object, that provides sending, getting messenges and listens for new to show them immediately
// function MiniChat is constructor , which displays MiniChat on the screen and allows user to chat via it. 
// * ls - LocalStorage object
if(!window.Messenger){
    if(!window.mlistener){
        var mlistener = mtemplListn = {
            update: function(){},
            createTries: 0
        };
    }
    var Messenger = {
        miniChatsOpened: {},
        audio: new Audio('/upload/w1.mp3'),
        lastsend: 0,
        init: function(){

            var _this = this;
            if(this._inited === true) return false;

            this._inited = true;

            $('.chat-pseudo-container').css({bottom: 0, maxWidth: 1350, left: 0});

            this.listenerChecker = setInterval(function(){
                if(Messenger.isListenerAlive() === false){
                    Messenger.createLister();
                }else if(Messenger.listenerKilled === true){
                    debugMess('Listener is killed');
                    clearInterval(Messenger.listenerChecker);
                }/*else{
                 debugMess('msgListener is already started');
                 }*/
            },5000 + rand(-100,100));

            if (window.addEventListener) {
                window.addEventListener("storage", function(e){
                    if(e.key !== Messenger.ls_msgs_key) return;
                    setTimeout(Messenger.onNewMsg(e), rand(10,50));
                }, false);
            } else {
                window.attachEvent("onstorage",function(e){
                    if(e.key !== Messenger.ls_msgs_key) return;
                    setTimeout(Messenger.onNewMsg(e), rand(10,50));
                } );
            }

            this.ls_msgs_key = 'msgs_'+ai.user_id+'_';
            this.ls_msg_listener = 'msgs_listener_'+ai.user_id+'_';
        },
        isListener: function(){
            return !!(ai.messenger_id === ls.get(Messenger.ls_msg_listener)[0]);
        },
        isListenerAlive: function(){
            if(Messenger.listenerKilled) return 0;

            var l = ls.get(Messenger.ls_msg_listener);
            if(l){
                if(l[0] != ai.messenger_id){
                    if(now() - parseInt(l[1]) > 4000 + rand(-100,100)){
                        debugMess('msg silent too long');
                        return false;
                    }
                }
            }else{
                debugMess('no msglistener at all');
                return false;
            }
            return true;
        },
        createLister: function(){
            debugMess('Starting new msg checker ..');
            var id = ai.messenger_id;
            if(!ai.messenger_id){
                debugMess('Listener messenger_id failed');
                return false;
            }else if(this.isListenerAlive()){
                debugMess('Listener already alive!!');
                return false;
            }

            var s = createEl('iframe',{src:'/a/msg_lstn.php',id:'msglistener'+id,name:'aiMsgListener'},{display:'none'});
            $('#aiUtils iframe[name="aiMsgListener"]').remove();
            if(!isEmpty($(s).prependTo('#aiUtils'))){

                mlistener = {
                    id: id,
                    iframe_id: 'msglistener'+id,
                    name: Messenger.ls_msg_listener,
                    started: now()-2000,
                    checked: now()-2000,
                    createTries: 1,
                    update: function(){
                        if(localStorage.login == 'false') return this.kill();

                        this.checked = now();
                        //debugMess(this.checked);
                        ls.set(this.name,[this.id, now()]);
                    },
                    kill: function(){
                        ls.remove(this.name);
                        $('iframe#'+this.iframe_id).remove();
                        mlistener = mtemplListn;
                        Messenger.listenerKilled = true;
                        clearInterval(this.listenerUpdater);
                        return false;
                    }
                };

                debugMess('Listener started');
                ls.set(mlistener.name,[mlistener.id,now()-2000]);

                mlistener.listenerUpdater = setInterval(function(){

                    mlistener.update();
                },2000 + rand(-100,100));

            }else{
                debugMess('Listener start failed');
            }
        },
        //////////////////////////////////////////////
        onNewMsg: function(e){
            var data = ls.get(e.key);

            if(this.isListener() && !data.no_income)
                this.audio.play();

            debugMess('New message, motherfucker!');

            this.onMiniChatMess(data.personal,!data.no_income);
            if(typeof DetailChat == 'object' && location.pathname == '/im/')
                DetailChat.onNewMsg(data);
            else
                console.log('adasdasd');
        },

        onMiniChatMess: function(data,should_update_counter){
            $.each(data,function(k,v){
                if(Messenger.miniChatsOpened['_' + v.from]){
                    Messenger.miniChatsOpened['_' + v.from].printMsgFromLS(v.msgs);
                }
                if(should_update_counter)
                    OL.addNewMsgsCnt(v.msgs.length, v.from);
            });

            return false;
        },
        //////////////////////////////////////////////

        getMiniChat: function(user_id,t){
            if(!this.miniChatsOpened['_'+user_id])
                this.miniChatsOpened['_'+user_id] = new MiniChat(user_id,t);
            this.miniChatsOpened['_'+user_id].show();
            return false;
        },

        sendMessage: function(data,s_callback,err_callback){
            if(this.lastsend < 1){
                this.lastsend = now();
            }else{
                if(Math.abs(now() - this.lastsend) < 500){
                    this.lastsend = now();
                    alert('Слишком часто отправляются сообщения.');
                    return false;
                }
                this.lastsend = now();
            }

            var dt = $.extend({
                act: 'send'
            },data);

            this.ajaxAct(dt,s_callback,err_callback);
        },
        getMoreMessages: function(offs,data,s_callback,err_callback){
            var dt = $.extend({
                act: 'more',
                offset: offs
            },data);
            this.ajaxAct(dt,s_callback,err_callback);
        },
        readAllMessages: function(data,s_callback,err_callback){
            var dt = $.extend({
                act: 'read_all'
            },data);
            this.ajaxAct(dt,s_callback,err_callback);
        },
        ajaxAct: function(data,s_callback,err_callback){
            var opts = {
                type: 'POST',
                url: '/a/messenger.php',
                dataType: "json",
                data: $.extend({
                    my_id: ai.user_id,
                    key: ai.user_key
                },data),
                error: function(data) {
                    console.log(data);
                }
            };

            if(typeof s_callback == 'function')
                opts.success = s_callback;

            if(typeof err_callback == 'function')
                opts.error = err_callback;

            $.ajax(opts)
        }
    };
}
try{
    ai.toExec.push('Messenger.init()');
}catch(e){

}

function MiniChat(pID, t){
    var chat_id;
    var p_id = pID;
    var chat_obj = $('#chat'+pID);
    var text_input;
    var msg_limit = 40;
    var scrollable;

    this.inited = false;
    this.no_more_msgs = false;
    this.processing_ajax = false;
    this.offset = 0;
    this.unchecked_cnt = 0;

    this.getUserId = function(){
        return p_id;
    };
    this.getChatObj = function(){
        return chat_obj;
    };
    this.getScrollable = function(){
        return scrollable;
    };
    this.getChatId = function(){
        return (chat_id) ? parseInt(chat_id)+'' : 'guest';
    };
    this.limit = function(){
        return msg_limit;
    };

    this.p_info =(t) ? {
        name: (t.textContent) ? t.textContent : 'Загрузка...',
        online: ($(t).siblings('i').hasClass('online')) ? true : false,
        href: (t.href) ? t.href : '#',
        avatar: $(t).siblings('a').children().attr('src')
    } : false ;

    //console.log(this.p_info);

    //drawing miniChat
    if(chat_obj.size() > 0){
        chat_obj.remove();
    }

    var _this = this;

    Messenger.ajaxAct({
        act: 'init',
        p_id: p_id,
        context: 'miniChat'
    },function(data){

        if(data.status == 'ok'){
            chat_id = data.id;
            _this.inited = true;
            var m = '';
            $.each(data.msgs, function(k,val){
                m = _this.msgTemplate(val,(val.from != ai.user_id)) + m;
                _this.offset++;
            });
            if(m == '')
                m = '<span class="no_msgs">Нет сообщений</span>';

            if(_this.offset >= _this.limit()) m = '<a class="more-message" onclick="console.log(\'more msgs\');">Ещё записи</a>' + m;


            if(data.user)
                _this.p_info = data.user;

            if(chat_obj.size() < 1)
                draw(_this.p_info);
            else
                _this.updateUserInfo(_this.p_info);

            if(data.checked && data.checked > 0){
                //OL.addNewMsgsCnt(data.checked*-1, pID);
                OL.clearMsgsCount(pID);
            }

            chat_obj.find('.mCSB_container').append(m);

            setTimeout(function(){_this.scrollMsg('last');debugMess(now());},50);
        }else{
            console.log(data);
        }
    });



    function draw(p_info){
        //console.log('draw',p_info);
    //(function(t){
        var content = '' +
        '<div id="chat'+pID+'"  class="chat-window">' +
            '<div class="chat-title">' +
                '<div class="handler"></div>' +
                '<i class="online-status '+((!!p_info.online) ? 'online' : 'offline')+'"></i>' +
                '<a href="'+p_info.href+'" class="name">'+p_info.name+'</a>' +
                '<a href="/im/?peer=p'+p_id+'" class="button-fullscreen"></a>' +
                '<span class="button-close" title="Close">' +
                    '<svg class="icon icon-close" viewBox="0 0 33 33" width="8" height="8"><use xlink:href="/images/icons.svg#icon-close"></use></svg>' +
                '</span>' +
            '</div>' +
            '<div class="messages-container">' +
                '<div class="scrollable-container">' +

                '</div>' +
            '</div>' +
            '<div class="input-block">' +
                '<div class="input-wrapper">' +
                    '<textarea class="autogrow-area"></textarea>' +
                    '<a class="mini-send-button"><svg class="icon icon-return" viewBox="0 0 39 32" width="11" height="9"><use xlink:href="/images/icons.svg#icon-return"></use></svg></a>' +
                '</div>' +
            '</div>' +
        '</div>';

        var ch_left = 0;
        if($('.chat-window').size() > 0){
            ch_left = $('.chat-window').eq(0).position().left;
            $('.chat-window').each(function(){
                // if($(this).offset().top < $(this).height())
                ch_left += $(this).width() + 5;
            });
            $('.chat-window').last().after(content);
        }else{
            $('body').append(content);
        }

        chat_obj = $('#chat'+pID);

        chat_obj.find(".autogrow-area").autogrow();
        chat_obj.draggable({
            containment: ".chat-pseudo-container",
            handle: ".chat-title",
            create: function(event, ui) {
                $(this).css({
                    top: $(this).position().top,
                    bottom: "auto",
                    left: (ch_left == 0) ? $(this).position().left : ch_left
                });
            }
        });

        //scrollable = chat_obj.find(".scrollable-container");

        scrollable = chat_obj.find(".scrollable-container").mCustomScrollbar({
            theme: "minimal",
            scrollInertia: 100
        });
        chat_obj.hide();
        text_input = chat_obj.find('textarea.autogrow-area');
        text_input.focus();
    }

    if(isObject(this.p_info))
        draw(this.p_info);

    chat_obj.on('keypress',"textarea.autogrow-area",function(e){
        if(e.keyCode == 13 && !e.shiftKey){
            _this.send();

            //console.log(_this);
            return false;
        }
    });

    chat_obj.on('focus',"textarea.autogrow-area",function(){
        _this.checkNewMsgs();
    });

    chat_obj.on('click',".mini-send-button",function(){
        _this.send();
        return false;
    });


    chat_obj.on('click',"a.more-message",function(e){
        _this.getMoreMsgs(_this.offset);
        return false;
    });


}


//// VISUAL ///////////////////////////////////////////////
MiniChat.prototype.show = function(){
    if(this.getChatObj()){
        this.getChatObj().show();
        this.getScrollable().mCustomScrollbar("update");
        setTimeout(this.scrollMsg('bottom'),100);
    }else{
        debugMess('bad chat html obj');
    }
};

MiniChat.prototype.scrollMsg= function(dest, speed){
    if(['top','bottom','last'].indexOf(dest) == -1) dest = 'bottom';
    //console.log(dest);
    //console.log(this);
    speed = parseInt(speed);
    if(this.getScrollable()){
       //console.log(this.getScrollable());
        this.getScrollable().mCustomScrollbar("scrollTo",dest,{
            scrollInertia:(speed === speed && speed > 0) ? speed : 100
        });
    }else{
        debugMess('bad html');
    }
};

MiniChat.prototype.closeChat = function(){
    if(this.getChatObj()){
        this.getScrollable().mCustomScrollbar("disable");
        this.getChatObj().hide();
    }else
        debugMess('bad chat html obj');
};
////////////////////////////////////////////////////

//// USER //////////////////////////////////////////
MiniChat.prototype.updateUserInfo = function(p_info){
    var obj = this.getChatObj();
    obj.find('i.online-status').removeClass().addClass('online-status '+((!!p_info.online) ? 'online' : 'offline'));
    obj.find('.chat-title a.name').attr('href',p_info.href).html(p_info.name);
};
////////////////////////////////////////////////////

//// MESSAGES //////////////////////////////////////
MiniChat.prototype.send = function(){
    if(!this.inited) return false;

    var _this = this;
    var text = this.getChatObj().find('textarea').val();

    var data = {
        peer: _this.getUserId()+'_'+_this.getChatId(),
        context: 'miniChat',
        text: text,
        ts: timePHP()
    };

    Messenger.sendMessage(data,function(d){
        if(d.status == 'ok'){
            if(_this.getChatObj().find('.no_msgs').size() > 0)
                _this.getChatObj().find('.no_msgs').remove();
            //console.log(_this.getChatObj().find('.mCSB_container'));
            _this.getChatObj().find('.mCSB_container').append(_this.msgTemplate({text:d.text, id: d.id}));
            _this.scrollMsg('bottom');
            _this.getChatObj().find('textarea').val('');
        }else{
            console.log(d);
        }
    });
};

MiniChat.prototype.getMoreMsgs = function(offset){
    if(!this.inited || this.no_more_msgs || this.processing_ajax ) return false;

    var _this = this;
    //return;
    this.processing_ajax = true;
    Messenger.getMoreMessages(offset,
        {
            peer: _this.getUserId()+'_'+_this.getChatId(),
            context: 'miniChat'
        },
        function(d){
        console.log(d);
        if(d.status == 'ok'){

            var m = '';
            var i = 0;
            $.each(d.msgs, function(k,val){
                m = _this.msgTemplate(val,(val.from != ai.user_id)) + m;
                i++;
            });
            _this.getChatObj().find('.more-message').after(m);

            if(i % _this.limit() > 0 || i == 0){
                _this.no_more_msgs = true;
                _this.getChatObj().find('.more-message').remove();
            }

            _this.offset += i;
        }



        setTimeout(function(){
            _this.processing_ajax = false;
        },500);
    });
};

MiniChat.prototype.checkNewMsgs = function(){
    if(this.unchecked_cnt > 0){
        var _this = this;
        Messenger.readAllMessages(
            {
                peer: _this.getUserId()+'_'+_this.getChatId(),
                context: 'miniChat'
            },
            function(data){
                if(data.status == 'ok'){
                    OL.clearMsgsCount(_this.getUserId());
                    _this.unchecked_cnt = 0;
                }
            }
        );
        return false;
    }
};
////////////////////////////////////////////////////

// onNewMsg
MiniChat.prototype.printMsgFromLS = function(data){
    if(isArray(data)){
        var _this = this;
        var m = '';
        var m_container = this.getChatObj().find('.mCSB_container')
        this.unchecked_cnt += data.length;
        $.each(data, function(k,val){
            //m = _this.msgTemplate(val,(val.from != ai.user_id)) + m;
            if(m_container.find('div#minimsg'+val.id).size() < 1){

                m_container.append(_this.msgTemplate(val,(val.from != ai.user_id)));
            }
        });

        //this.updateNewMsgsCnt();
        //OL.updateNewMsgsCnt(data.length, this.getUserId());
        _this.scrollMsg('bottom');
    }
};
////////////////////////////////


// TPL
MiniChat.prototype.msgTemplate = function(data,to_me){
    if(!data.text) return false;
    return (to_me === true) ?
        '<div id="minimsg'+data.id+'" class="message friend-message">'+
            '<div class="avatar-link"><a href="'+this.p_info.href+'" title="'+this.p_info.name+'" target="_blank"><img class="photo" src="'+this.p_info.avatar+'" alt=""></a></div>'+
            '<div class="text">'+data.text+'</div>'+
            '</div>'
        :
        '<div  id="minimsg'+data.id+'" class="message user-message">'+
            '<div class="text">'+data.text+'</div>'+
            '</div>';
};
