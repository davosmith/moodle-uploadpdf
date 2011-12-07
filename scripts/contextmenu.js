/* Modified version of the code by David Walsh found at:
   http://davidwalsh.name/mootools-context-menu
*/

var ContextMenu = new Class({

	//implements
	Implements: [Options,Events],

	//options
	options: {
		actions: {},
		menu: 'contextmenu',
		stopEvent: true,
		targets: 'body',
		trigger: 'contextmenu',
		offsets: { x:0, y:0 },
		onShow: function() {},
		onHide: function() {},
		onClick: function() {},
		fadeSpeed: 200
	},
	
	//initialization
	initialize: function(options) {
		//set options
		this.setOptions(options)
		
		//option diffs menu
		this.menu = document.id(this.options.menu);
		this.targets = $$(this.options.targets);

		//fx
		//this.fx = new Fx.Tween(this.menu, { property: 'opacity', duration:this.options.fadeSpeed });
		
		//hide and begin the listener
		this.hide().startListener();
		
		//hide the menu
		this.menu.setStyles({ 'position':'absolute','top':'-900000px', 'display':'block' });
	},
	
	//get things started
	startListener: function() {
		/* all elements */
		this.targets.each(this.addmenu, this);
		
		/* menu items */
		this.menu.getElements('a').each(function(item) {
			item.addEvent('click',function(e) {
				if(!item.hasClass('disabled')) {
					this.execute(item.get('href').split('#')[1],document.id(this.options.element));
					this.fireEvent('click',[item,e]);
				}
			}.bind(this));
		},this);
		
		//hide on body click
		document.id(document.body).addEvent('click', function() {
			this.hide();
		}.bind(this));
	},

	addmenu: function(target) {
	    /* show the menu */
	    target.addEvent(this.options.trigger,function(e) {
		    //enabled?
		    if(!this.options.disabled) {
			//prevent default, if told to
			if(this.options.stopEvent) { e.stop(); }
			//record this as the trigger
			this.options.element = document.id(target);
			//position the menu
			this.menu.setStyles({
				    position: 'absolute',
				    'z-index': '2000'
				    });
			var offx = this.options.offsets.x;
			var offy = this.options.offsets.y;
			// Nasty hack to fix positioning problem in IE <= 7 (IE 8 seems fine)
			if (Browser.ie6 || Browser.ie7) {
			    offx -= 10;
			}
			this.menu.setPosition({x: (e.page.x + offx), y: (e.page.y + offy) });
			//show the menu
			this.show();
		    }
		}.bind(this));
	},
	
	//show menu
	show: function(trigger) {
	        //this.menu.fade('in');
		//this.fx.start(1);
		this.fireEvent('show');
		this.shown = true;
		return this;
	},
	
	//hide the menu
	hide: function(trigger) {
		if(this.shown)
		{
		    //this.fx.start(0);
		    //this.menu.fade('out');
		    this.menu.setStyles({ 'top':'-900000px' });
		    this.fireEvent('hide');
		    this.shown = false;
		}
		return this;
	},
	
	//disable an item
	disableItem: function(item) {
		this.menu.getElements('a[href$=' + item + ']').addClass('disabled');
		return this;
	},
	
	//enable an item
	enableItem: function(item) {
		this.menu.getElements('a[href$=' + item + ']').removeClass('disabled');
		return this;
	},

	addItem: function(item, text, deleteicon, func, titletext) {
	    var newel = new Element('li');
	    var link = new Element('a');
	    link.set('html',text);
	    link.setProperty('href','#'+item);
	    if (titletext) {
		link.setProperty('title',titletext);
	    }
	    newel.adopt(link);
	    if (deleteicon) {
		var dellink = new Element('a');
		var delico = new Element('img');
		delico.setProperty('src', deleteicon);
		dellink.setProperty('href','#del'+item);
		dellink.setProperty('class', 'delete');
		dellink.adopt(delico);
		dellink.addEvent('click', function(e) {
			this.execute('removeitem',item)
		    }.bind(this) );
		newel.adopt(dellink);
	    }

	    newel.set('id',this.options.menu + item);
	    this.menu.adopt(newel);
	    //	    this.options.actions[item] = func;
	    link.addEvent('click',function(e) {
		    if(!link.hasClass('disabled')) {
			//this.execute(link.get('href').split('#')[1],document.id(this.options.element));
			func(item,this);
			this.fireEvent('click',[link,e]);
		    }
		}.bind(this));

	    return this;
	},

	removeItem: function(item) {
	    var remove = document.getElementById(this.options.menu + item);
	    if (remove) { remove.destroy(); }
	    return this;
	},
	
	//diable the entire menu
	disable: function() {
		this.options.disabled = true;
		return this;
	},
	
	//enable the entire menu
	enable: function() {
		this.options.disabled = false;
		return this;
	},
	
	//execute an action
	execute: function(action,element) {
		if(this.options.actions[action]) {
			this.options.actions[action](element,this);
		}
		return this;
	}
	
});
