(function($) {
    function NavGraph(pageElement, flotOptions, xMin, xMax){
        var BASE_DIR = "js/flot/";
        var _thisNavGraph = this;       
        var _container = pageElement;
        var _panning;
        var _zooming;
        var _ajaxGif;

        var _flotOptions = {
            nav: {
                moveInterval: 1000
            },
            zoom: {
                interactive: true,
                amount: 1.25
            },
            pan: {
                interactive: true
            },
            legend: {
                position: "nw",
                noColumns: 5
            },
            xaxis: {
                insertGaps: true,
                gapColor: "rgba(0,0,0,0.05)",
                mode: "time",
                min: xMin,
                max: xMax
            },
            yaxis: {
                zoomRange: [0, 0]
            },
            caching: {
                enabled: true,
                binSizeMin: 3000000
            }
        };
        
        _flotOptions = $.extend(true, {}, _flotOptions, flotOptions);
        
        var _navGraphController = $.navctrl(_container, _flotOptions);
        
        this.buildNavControls = function() {
            _addMainControls();
        };
        
        this.buildSnapControls = function(container, defaultRange) {
            _buildSnapControls(container, defaultRange);
        };
        
        this.getController = function(){
            return _navGraphController;
        };
        
        _container.bind('cacherarrived', function(event, series){
            _navGraphController.setSeries(series);
            _navGraphController.draw();
        });
        
        _container.bind('cacherfree', function(event){
            if (_ajaxGif) {
                _ajaxGif.fadeOut();
            }
        });
        
        _container.bind('cacherbusy', function(event){
            if (_ajaxGif) {
                _ajaxGif.show();
            }
        });
        
        _container.bind('plotredraw', function(event){
            if (_drawNavControls) {
                _addMainControls();
            }
        });
        
        /**
        * Bind the plot pan event
        */
        _container.bind('plotpan', function(event, plot){
            // notify next pan interval that we have moved
            _panning = plot.getAxes().xaxis;
        });
        
        /**
        * Bind the plot zoom event
        */
        _container.bind('plotzoom', function(event, plot){
            // notify next zoom interval that we have moved
            _zooming = plot.getAxes().xaxis;
        });
        
        /**
        * Restrict pan reloading to something sane
        */
        setInterval(function(){
            if (_panning) {
                _navGraphController.panRequest(_panning.min, _panning.max);
                _panning = undefined;
            }
        }, _flotOptions.nav.moveInterval);
        
        /**
        * Restrict zooming
        */
        setInterval(function(){
            if (_zooming) {
                _navGraphController.zoomRequest(_zooming.min, _zooming.max);
                _zooming = undefined;
            }
        }, _flotOptions.nav.moveInterval);
        
        /**
        * Helper function for adding nav arrows
        */
        var _addArrow = function(dir, right, top, offset){
            $('<img class="navButton" src="' + BASE_DIR + 'arrow-' + dir +
            '.gif" style="right:' +
            right +
            'px;top:' +
            top +
            'px">').appendTo(_container).click(function(e){
                e.preventDefault();
                _navGraphController.pan(offset);
            });
        }
        
        /**
         * builds up the snap to controls and add them to the given container
         */
        var _buildSnapControls = function(container, defaultRange) {
            var selectItems = ["hour", "day", "week", "month", "year"],
            selectBox, prevButton, nextButton, snapButton;
            
            var _updateButtons = function() {
                nextButton.val(selectBox.val() + ' >>');
                prevButton.val('<< ' + selectBox.val());
                snapButton.val('Snap to ' + selectBox.val());
                
            };

            // initial text
            container.append("<strong>Snap Range:</strong>");

            // build up the drop down box
            selectBox = $('<select></select>').appendTo(container);
            $.each(selectItems, function(val, text) {
                selectBox.append($('<option></option').val(text).html(text));
            });

            container.append("<strong> | Jump To: </strong>");

            // prev range button
            prevButton = $('<input type ="button" value="Prev" />').appendTo(container).click(function(e) {
                var xaxis = _navGraphController.prevRange(selectBox.val());
                _navGraphController.zoomRequest(xaxis.min, xaxis.max);
            });

            // build up time navigation buttons
            $('<input type ="button" value="Now" />').appendTo(container).click(function(e) {
                var xaxis = _navGraphController.now(selectBox.val());
                _navGraphController.zoomRequest(xaxis.min, xaxis.max);
            });

            // next range button
            nextButton = $('<input type ="button" value="Next" />').appendTo(container).click(function(e) {
                var xaxis = _navGraphController.nextRange(selectBox.val());
                _navGraphController.zoomRequest(xaxis.min, xaxis.max);
            });
            
            container.append("<strong> | </strong>");

            snapButton = $('<input type ="button" value="Snap to" />').appendTo(container).click(function(e){
                var xaxis = _navGraphController.snapTo(selectBox.val());
                _navGraphController.zoomRequest(xaxis.min, xaxis.max);
            });

            // change selected item
            selectBox.change(function() {
                _updateButtons();
            });

            selectBox.val(defaultRange);
            _updateButtons();
        };
        
        /**
        * Overlay all the controls onto the main graph
        */
        var _addMainControls = function(){
            // add zoom out button 
            $('<div class="navButton" style="right:45px;top:100px">zoom out</div>').appendTo(_container).click(function(e){
                e.preventDefault();
                _navGraphController.zoomOut();
            });
            
            // add zoom in button 
            $('<div class="navButton" style="right:47px;top:20px">zoom in</div>').appendTo(_container).click(function(e){
                e.preventDefault();
                _navGraphController.zoom();
            });
            
            //add loading gif 
            _ajaxGif = $('<div id="al" class="navButton" style="right:300px;top:101px"><img src="' +
            BASE_DIR +
            'ajax-loader.gif"/></div>').appendTo(_container);
            _ajaxGif.hide();
            
            // and add panning buttons
            _addArrow('left', 80, 60, {
                left: -100
            });
            _addArrow('right', 50, 60, {
                left: 100
            });
            _addArrow('up', 65, 45, {
                top: -100
            });
            _addArrow('down', 65, 75, {
                top: 100
            });
        }        
    }
    
    $.navgraph = function(pageElement, flotOptions, xMin, xMax) {
            return new NavGraph($(pageElement), flotOptions, xMin, xMax);
    };
})(jQuery);
