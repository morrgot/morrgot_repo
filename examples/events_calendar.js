if(!window.EventsCalendar){

    var EventsCalendar = {
        inited: false,
        events: [],
        event_types: {},
        container: {},
        init: function(){
            if(this.inited) return;

            var _this = this;
            $('.graphic-ads').remove();

            this.container = $('#calendar');

            this.container.fullCalendar({
                header: {
                    left: 'today',
                    center: 'prev title next',
                    right: 'month,agendaWeek,agendaDay'
                },
                viewRender: function( view, element ){
                    if($('div.fc-toolbar .left_stolb, div.fc-toolbar .right_stolb').size() < 2)
                        element.parent().siblings('.fc-toolbar').eq(0).append('<div class="left_stolb"></div><div class="right_stolb"></div>');
                },
                lang: 'ru',
                defaultDate: new Date,//'<?=date('Y-m-d')?>',
                timezone: 'local',
                selectable: true,
                selectHelper: true,
                select: function(start, end) {
                    var date_s = start.toDate();
					var date_e = end.toDate();
					//console.log(start, date_s,end);
                    if(!start.hasTime() || !end.hasTime())
					    return false;

                    //console.log(date_s);
                    _this.fillEditForm({
                        notify: '0',
                        type: '1',
                        date_s: date_s,
                        date_e: date_e
                    });

                    _this.openEditForm('Добавить событие:','add');
                },
                dayClick: function(date, jsEvent, view) {
                    // change the day's background color just for fun
					_this.getTodayEvents(date.format());

                },
                eventClick: function(calEvent, jsEvent, view) {
                    _this.eventEditForm(calEvent.id);
                },
                editable: false,
                eventRender: function(event, element) {
                    //console.log(event);
                    //element.html('element' + now());
                },
                eventLimit: true, // allow "more" link when too many events
                events: this.events,
				dayNamesShort: ['Воскресенье', 'Понедельник', 'Вторник', 'Среда', 'Четверг', 'Пятница', 'Суббота'],
				timeFormat: 'HH:mm' 
            });

            $('#calendar').on('click','.fc-right .fc-button-group button', function(){
                if($(this).hasClass('fc-month-button'))
                    location.hash = 'month';
                else if($(this).hasClass('fc-agendaWeek-button'))
                    location.hash = 'week';
                else if($(this).hasClass('fc-agendaDay-button'))
                    location.hash = 'day';
            });

            switch(location.hash){
                case '#day': $('button.fc-agendaDay-button').click(); break;
                case '#week': $('button.fc-agendaWeek-button').click(); break;
            }

            $(".hour, .minutes").keydown(function(e){
                if((e.keyCode >= 65 && e.keyCode <= 90) || (e.keyCode >= 106 && e.keyCode <= 111) || (e.keyCode >= 187 && e.keyCode <= 222) || ':;№!@#$%^&*()"\''.indexOf(e.key) > -1 || [0,59,61,32,173].indexOf(e.keyCode) > -1)
                    return false;
            });


            $(".hour").blur(function(){
                var value=parseInt($(this).val())
                if(value>23)
                { $(this).val(23);
                    return
                }
                if(value<0||(!value))
                {
                    $(this).val("00");
                    return
                }
                if(value<10)
                {
                    $(this).val("0"+value);
                }
            });

            $('.minutes').blur(function(){
                var value=parseInt($(this).val())
                if(value>59)
                { $(this).val(59);
                    return
                }
                if(value<0||(!value))
                {
                    $(this).val("00");
                    return
                }
                if(value<10)
                {
                    $(this).val("0"+value);
                }
            });

            this.event_form().find('.datepicker').keydown(function(){return false;});

            this.inited = true;
        },

        editEvent: function(){
            return this.saveEvent(this.event_form().find('#event_id').val());
        },

        deleteEvent: function(){
            var e_id = this.event_form().find('#event_id').val();
            var _this = this;

            this.ajaxAct({
                act: 'delete',
                e_id: e_id
            },function(data){
                if(data.status == 'ok'){
                    $.fancybox.close();
                    _this.container.fullCalendar( 'removeEvents' , e_id  );
                }
            });
            return false;
        },

        saveEvent: function(id){
            var form = this.event_form();
            if(form.size() < 1) return false;

            var d = {
                title: form.find('#event_title').val().trim(),
                description: form.find('#event_description').val().trim(),
                type: form.find('#event_type').val().trim(),
                notify: form.find('#event_notify').val() * 1
            };

            if(id){
                d.e_id = id;
            }

            if(d.title == '')
                return this.submitError('Заполните название события!');

            var date = $('#event_date_start').val().split(".");
            var date_s = date[1]+"/"+date[0]+"/"+date[2];
            var time_s = $('#event_hour_start').val().trim() + ':' + $('#event_minute_start').val().trim();

            d.start = parseInt(new Date(date_s + ' ' + time_s).getTime() / 1000);
            if(d.start !== d.start || d.start < 1)
                return this.submitError('Некорректное время начала!');

            date = $('#event_date_end').val().split(".");
            date_s = date[1]+"/"+date[0]+"/"+date[2];
            time_s = $('#event_hour_end').val().trim() + ':' + $('#event_minute_end').val().trim();

            d.end = parseInt(new Date(date_s + ' ' + time_s).getTime() / 1000);
            if(d.end !== d.end || d.end < 1)
                return this.submitError('Некорректное время окончания!');

            if(d.end < d.start)
                return this.submitError('Некорректное время начала и окончания!');


            //return false;
            var _this = this;
            this.ajaxAct({
                act: 'set_event',
                event: d
            },function(data){
                if(data.status == 'ok'){
                    var eventData = {};
                    eventData.id = data.id;

                    var method = 'renderEvent';

                    if(id){
                        method = 'updateEvent';
                        eventData = _this.container.fullCalendar( 'clientEvents', id )[0];
                    }

                    eventData.title = data.event.title;
                    eventData.description = data.event.description;
                    eventData.start = ts2date(data.event.start, 'obj').toISOString();
                    eventData.end = ts2date(data.event.end, 'obj').toISOString();
                    eventData.id = data.event.id;

                    //console.log(eventData, eventData.title);
                    _this.events.push(eventData);
                    _this.container.fullCalendar(method, eventData); // stick? = true
                    _this.container.fullCalendar('unselect');
                    //_this.container.fullCalendar('refetchEvents');
                    form.find('input[type="text"],textarea').val('');
                    form.find('input.hour,input.minutes').val('00');
                    $.fancybox.close();
                }
            });

            return false;

        },
		
		showAddForm: function(){
			var s = new Date();
			s.setHours(0);
			s.setMinutes(0);
			var e = new Date();
			e.setHours(0);
			e.setMinutes(0);
			e.setDate(e.getDate() + 1);
			//console.log(date_s);
			this.fillEditForm({
				notify: '0',
				type: '1',
				date_s: s,
				date_e: e
			});

			this.openEditForm('Добавить событие:','add');
			
			return false;
		},
		
        eventEditForm: function(id){
            var _this = this;
            //console.log(event);
            this.ajaxAct({
                act: 'event_details',
                e_id: id
            },function(data){
                if(data.status == 'ok'){
                    _this.fillEditForm({
                        id: data.event.id,
                        title: data.event.title,
                        description: data.event.description,
                        notify: data.event.notify,
                        type: data.event.type,
                        date_s: ts2date(data.event.start, 'obj'),//event.start.toDate(),
                        date_e: ts2date(data.event.end, 'obj')//event.end.toDate()
                    });
                    _this.openEditForm('Событие:','edit');
                }
            });
			return false;
        },

        event_form: function(){
            return $('#event-add-form > form');
        },

        openEditForm: function(title, butt_type){
            var form  = this.event_form();
            //console.log(form, form.find('input.button-main'));

            form.find('.title .param').html(title);

            form.find('input.button-main').hide();
            if(butt_type == 'edit')
                form.find('input.button-main.event_edit').show();
            else if(butt_type == 'add')
                form.find('input.button-main.event_add').show();

            $.fancybox.open({
                href: '#' + form.parent()[0].id
            });
        },

        fillEditForm: function(o){
            var form = this.event_form();
            var date_s = o.date_s;
            var date_e = o.date_e;


            if(o.id){
                if(form.find('#event_id').size() < 1)
                    form.append('<input type="hidden" value="'+ o.id+'" name="event_id" id="event_id" />');
                else
                    form.find('#event_id').val(o.id);
            }else{
                form.find('#event_id').remove();
            }

            form.find('#event_title').val(o.title);
            form.find('#event_description').val(o.description);

            form.find('#event_date_start').datepicker( "setDate", date_s );
            form.find('#event_date_end').datepicker( "setDate", date_e );

            form.find('#event_hour_start').val(date_s.getHours() );
            form.find('#event_minute_start').val(date_s.getMinutes() );

            form.find('#event_hour_end').val(date_e.getHours() );
            form.find('#event_minute_end').val(date_e.getMinutes() );

            form.find('.hour, .minutes').each(function(){
                var v = $(this).val();
                if(v.length  < 2)
                    $(this).val('0' + v);
            });

            form.find('#event_notify').selectpicker('val', (o.notify) ? o.notify : '0');

            form.find('#event_type').selectpicker('val', o.type);

        },
		
		getTodayEvents: function(date_str){
			var date_arr = date_str.split('-');
			var _this = this;
			this.ajaxAct({
                act: 'today_events',
                date: date_str
            },function(data){
				var c = '';
                if(data.status == 'ok'){
                    $.each(data.events,function(k,v){
						c += _this.curDayEventTempl(v);
					});
					$('#today-container').html(c);
					$('#cur_event_date').html(data.date);
                }
            });
			return false;
		},
		
		curDayEventTempl: function(event){
			return ''+
				'<div id="t_event' +event.id + '" class="text_calend">' +
					'<em><img src="' + this.event_types[event.type].icon + '" width="22" height="27"></em>' +
					'<p><b>' +event.title + '</b> ' +event.description + ' <a href="#" onclick="return EventsCalendar.eventEditForm(' +event.id +')">Подробнее</a></p>' +
				'</div>';
		},

        ajaxAct: function(data,s_callback,err_callback){
            var opts = {
                type: 'POST',
                url: '/a/agent.php',
                dataType: "json",
                data: $.extend({
                    my_id: ai.user_id,
                    key: ai.user_key,
                    context: 'events_calendar'
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
        },
        submitError: function(s){
            alert(s);
            return false;
        }

    }
}

try{ai.toExec.push( "EventsCalendar.init();");}catch (e){}
