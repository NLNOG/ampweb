/**
 * Choose the type of graph to draw
 */
(function($) {
    function AmpGraph(container, options, dataset, type, mesh){
        // choose the type of graph to draw
        switch (type) {
            case "day":
                if (!mesh) return $.amptimeview(container, options);
                else return $.ampmeshtimeview(container, options);
            case "week":
                if (!mesh) return $.amptimeview(container, options);
                else return $.ampmeshtimeview(container, options);
            case "month":
                if (!mesh) return $.amptimeview(container, options);
                else return $.ampmeshtimeview(container, options);
            default:
                return $.ampgraphview(container, options, dataset);
        }
    }
    
    $.ampgraph = function(container, options, dataset, type, mesh) {
            return new AmpGraph($(container), options, dataset, type, mesh);
    };
})(jQuery);

/**
 * AmpGraphView is the standard view for basic graphs
 */
(function($) {
    function AmpGraphView(container, options, dataset){
        var _thisAmpLineView = this;
        
        var _container = container;
        var _options = options;
        var _dataset = dataset;
        
        var _flotContainer = $('<div class="flotgraph" style="width:625px;height:250px"></div>').appendTo(_container);
        //var _title = _options.title;
        
        // main graph options
        var _flotOptions = {
            legend: {
                position: "nw",
                noColumns: 5
            },
            lines: {
                lineWidth: 1
            },
            points: {
                show: true,
                radius: 0.25
            },
            xaxis: {
                min: 0
            },
            yaxis: {
                min: 0
            }
        };
        
        var _initialise = function() {
            // draw flot
            var data = [], color;

            // merge defaults with provided options
            _options = $.extend(true, {}, _flotOptions, _options);

            $.each(_dataset.data, function(index, point) {
                data.push([point.x, point.y]);
            });

            // if the series comes with a color, add it in
            if (_dataset.color) color = _dataset.color;
            else color = "#f00";

            var series = [{ data: data, color: color, shadowSize: 0 }];

            var plot = $.plot(_flotContainer, series, _options);
            var style = { font: "80% Arial, Helvetica", fill: "rgb(0,0,0)" };

            // draw the x label
            if (_options.xaxis.label)
                plot.drawXaxisLabel(_options.xaxis.label, style, "bottom");
            
            // draw the y label
            if (_options.yaxis.label)
                plot.drawYaxisLabel(_options.yaxis.label, style, "left");
        };
        
        _initialise();
    }
    
    $.ampgraphview = function(container, options, dataset) {
            return new AmpGraphView($(container), options, dataset);
    };
})(jQuery);

/**
 * AmpTimeNavView graphs are ajax driven time series navigation graphs
 */
(function($) {
    function AmpTimeNavView(container, options){
        var _thisAmpLineView = this;
        
        var _container = container;
        var _options = options;
        
        var _title = $("<div></div>").appendTo(_container);
        var _flotContainer = $('<div class="flotgraph" style="width:675px;height:250px"></div>').appendTo(_container);
        var _navGraph;
        var _txtLinkArea;
        var _checkboxes = [];
        var _ctrl;
        
        // main graph options
        var _flotOptions = {
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
            lines: {
                show: true,
                lineWidth: 1.25
            },
            xaxis: {
                insertGaps: true,
                gapColor: "rgba(0,0,0,0.05)",
                tzOffset: options.xaxis.tzOffset,
                zoomRange: [MIN_RANGE, MAX_RANGE],
                mode: "time",
                tickFormatter: function(val, axis){
                    var range = axis.max - axis.min, date = new Date(val).toUTCString();

                    // scrub out the timezone part to stop conversion
                    date = date.substr(0, date.length - 4);

                    if (range < 2 * 24 * 60 * 60 * 1000) 
                        return new Date(date).toString("H:mm");
                    if (range < 5 * 24 * 60 * 60 * 1000) 
                        return new Date(date).toString("ddd HH:mm");
                    if (range < 10 * 24 * 60 * 60 * 1000) 
                        return new Date(date).toString("ddd MMM d");
                    if (range < 31 * 24 * 60 * 60 * 1000) 
                        return new Date(date).toString("MMM d");
                    
                    return new Date(date).toString("MMM d, yyyy");
                }
            },
            yaxis: {
                scaleMode: "visible",
                min: 0,
                zoomRange: [0, 0],
                panRange: [0, 60000]
            },
            y2axis: {
                autoscaleMargin: 0,
                min: 0,
                max: 1,
                color: "#545454",
                ticks: 6,
                zoomRange: [0, 0],
                panRange: [0, 1],
                tickFormatter: function(val, axis){
                    return Math.round(val * 100) + "%";
                }
            },
            caching: {
                enabled: true,
                binSizeMin: 300000,
                dataFunction: _options.module.dataFunction,
                boundaries: _options.boundaries
            },
            hooks: {
                draw: [function(plot, ctx) {
                    var opts = plot.getOptions(), xaxis = plot.getAxes().xaxis.options;
                    if (!opts.caching.boundaries) return;
                    
                    xaxis.panRange = [opts.caching.boundaries.startTime - (xaxis.max - xaxis.min),
                        opts.caching.boundaries.endTime + (xaxis.max - xaxis.min)];
                }]
            }
        };
        
        /**
        * Bind the plot pan event
        */
        _flotContainer.bind('plotpan', function(event, plot){
            if (_txtLinkArea) _txtLinkArea.hide();
        });
        
        /**
        * Bind the plot zoom event
        */
        _flotContainer.bind('plotzoom', function(event, plot){
            if (_txtLinkArea) _txtLinkArea.hide();
        });

        var _addControls = function(){
            var all = $('<div id="settings" style="width:675px;margin-left:auto;margin-right:auto;margin-bottom:4px ;font-size:smaller"></div>').appendTo(_container);
            var options = $('<div style="text-align:center"></div>').appendTo(all);
            var snap = $('<div style="text-align:center"></div>').appendTo(all);
            var buttons = $('<div style="text-align:center"></div>').appendTo(all);
            var sections = _options.module.inputFields();
    
            options.append('Y-axis max:');
            
            $('<input class="graphoptions" type="text" size="3" maxlength="4">').appendTo(options).keyup(function(e){
                var num = $(this).val();
                if (_isNumeric(num)) {
                    _ctrl.moveTo({ yaxis: { min: 0, max: parseFloat(num) }, n: 1  });
                }
            });
            
            for (var section in sections) {
                options.append(' | <strong>' + section + ':</strong>');
                
                for (var i = 0; i < sections[section].length; i++) {
                    var thisBox = sections[section][i];
                    thisBox.appendTo(options);
                    $(options).append(thisBox[0].name + " ");
                    _checkboxes.push(thisBox);
                }
            }
            
            $.each(_checkboxes, function(index, checkbox){
                checkbox.click(_setSelectedOptions);
            });

            _navGraph.buildSnapControls(snap, _options.boundaries.startingRange);
            
            $('<button>Auto Scale</button>').appendTo(buttons).click(function(e){
                _ctrl.autoScale();
            });
            
            $('<button>Get Link</button>').appendTo(buttons).click(function(e){
                _getLink();
            });
            
            buttons.append('<br />');
            
            _txtLinkArea = $('<textarea class="graphOptions" onClick="this.focus();this.select();" readonly rows="4" cols="50"></textarea>').appendTo(buttons);
            
            _txtLinkArea.hide();
        }
        
        var _setSelectedOptions = function(){
            var options = [];
            
            // make sure there are options
            if (_checkboxes.length > 1) {
                $.each(_checkboxes, function(index, checkbox){
                    if (checkbox[0].checked == true) {
                        options.push(checkbox[0].name);
                    }
                });
                
                _ctrl.setSelected(options);
                _ctrl.draw();
            }
        };
        
        /**
        * Upates the graph title to show the current time range
        */
        var _updateTitle = function() {
            var axes = _ctrl.getUsedAxes(), startms, endms; 
            if (axes[0]) {
                startms = axes[0].min;
                endms = axes[0].max;
            }

            // check for no axis data
            if (!startms || !endms) return;

            var start = new Date(startms).toUTCString();
            var end = new Date(endms).toUTCString(); 
            start = start.substr(0, start.length -4);
            end = end.substr(0, end.length -4);
            var startDate = new Date(start);

            // less than of equal to a days difference
            if (endms - startms <= MS_1HOUR) {
                _title.html("<h3>" +
                    new Date(start).toString("h:mm tt ddd MMM d yyyy") +
                    "</h3>"
                );

            // when there is exactly a day difference - display only a day
            } else if (startms == endms - MS_1DAY && startDate.toString("ssmmHH") == "000000") {
                _title.html("<h3>" +
                    new Date(start).toString("ddd MMM d yyyy") +
                    "</h3>"
                );

            // exactly a week difference
            } else if (startms == endms - MS_1WEEK && startDate.is().sunday()) {
                _title.html("<h3>Week starting " +
                    new Date(start).toString("ddd MMM d yyyy") +
                    "</h3>"
                );

            // display the standard title
            } else {
                _title.html("<h3>" +
                    new Date(start).toString("ddd MMM d yyyy") +
                    " to " +
                    new Date(end).toString("ddd MMM d yyyy") +
                    "</h3>"
                );
            }
        };
        
        /**
        * Get the graph URL
        */
        var _getLink = function(){
            var axes = _ctrl.getGraphRange();
            var url = _options.pageUrl;
            
            // add axes options to the url
            url += "&use=1" +
            "&xmin=" +
            axes.xaxis.min +
            "&xmax=" +
            axes.xaxis.max +
            "&ymin=" +
            axes.yaxis.min +
            "&ymax=" +
            axes.yaxis.max +
            "&boxes=";
            
            // add the ticked boxes to the url
            for (var i = 0; i < _checkboxes.length; i++) {
                var checkbox = _checkboxes[i][0];
                
                if (checkbox.checked) 
                    url += checkbox.name + "_";
            }
            
            // remove the last divider
            if (url.substr(url.length - 1) == "_") 
                url = url.substr(0, url.length - 1);
            
            // output the url
            _txtLinkArea.val(url);
            _txtLinkArea.show();
        };
        
        /**
        * Return true if text contains all numbers
        */
        var _isNumeric = function(sText){
            if (sText == "") {
                return false;
            }
            
            var ValidChars = "0123456789.";
            var IsNumber = true;
            var Char;
            for (i = 0; i < sText.length && IsNumber == true; i++) {
                Char = sText.charAt(i);
                if (ValidChars.indexOf(Char) == -1) {
                    IsNumber = false;
                }
            }
            return IsNumber;
        };
        
        _container.bind('plotmove', function(event, axes){
            _updateTitle();
        });
        
        var _initialise = function(){
            var style = { font: "80% Arial, Helvetica", fill: "rgb(0,0,0)" };
            var side = "left";

            _navGraph = $.navgraph(_flotContainer, _flotOptions, _options.xaxis.min, _options.xaxis.max);

            _ctrl = _navGraph.getController();
            
            // draw the x label
            if (_options.xaxis.label)
                _ctrl.drawXaxisLabel(_options.xaxis.label, style, "bottom");
            
            // draw the y label
            if (_options.yaxis.label) {
                if (_options.yaxis.label.indexOf("loss") === 0)
                    side = "right";
                _ctrl.drawYaxisLabel(_options.yaxis.label, style, side);
            }
            
            _addControls();
            _setSelectedOptions();
            _navGraph.buildNavControls();
        };
        
        _initialise();
    }
    
    $.amptimeview = function(container, options) {
            return new AmpTimeNavView($(container), options);
    };
})(jQuery);

/**
 * AmpMeshTimeNavView graphs are ajax driven time series navigation graphs
 * Multiple sources or destinations are visualised using coloured points
 */
(function($) {
    function AmpMeshTimeNavView(container, options){
        var _thisAmpLineView = this;
        
        var _container = container;
        var _options = options;
        
        var _title = $("<div></div>").appendTo(_container);
        var _heatTitle = $("<h4>" + _options.title.text + "</h4>").appendTo(_container);
        var _flotContainer = $('<div class="flotgraph" style="width:675px;height:250px"></div>').appendTo(_container);
        var _navGraph;
        var _txtLinkArea;
        var _ctrl;

        // main graph options
        var _flotOptions = {
            insertGaps: true,
            zoom: {
                interactive: true,
                amount: 1.25
            },
            pan: {
                interactive: true
            },
            legend: {
                show: false,
                position: "nw",
                noColumns: 5
            },
            heatmap: {
                show: true,
                legend: {
                    show: true,
                    label: _options.heatmap.legend.label
                }
            },
            xaxis: {
                insertGaps: true,
                tzOffset: options.xaxis.tzOffset,
                zoomRange: [MIN_RANGE, MAX_RANGE],
                mode: "time",
                tickFormatter: function(val, axis){
                    var range = axis.max - axis.min, date = new Date(val).toUTCString();

                    // scrub out the timezone part to stop conversion
                    date = date.substr(0, date.length - 4);

                    if (range < 2 * 24 * 60 * 60 * 1000) 
                        return new Date(date).toString("H:mm");
                    if (range < 5 * 24 * 60 * 60 * 1000) 
                        return new Date(date).toString("ddd HH:mm");
                    if (range < 10 * 24 * 60 * 60 * 1000) 
                        return new Date(date).toString("ddd MMM d");
                    if (range < 31 * 24 * 60 * 60 * 1000) 
                        return new Date(date).toString("MMM d");
                    
                    return new Date(date).toString("MMM d, yyyy");
                }
            },
            yaxis: {
                scaleMode: "visible",
                min: 0,
                zoomRange: [0, 0],
                panRange: [0, 20]
            },
            caching: {
                enabled: true,
                binSizeMin: 300000,
                dataFunction: _options.module.dataFunction,
                boundaries: _options.boundaries
            },
            hooks: {
                draw: [function(plot, ctx) {
                    var opts = plot.getOptions(), xaxis = plot.getAxes().xaxis.options;
                    if (!opts.caching.boundaries) return;
                    
                    xaxis.panRange = [opts.caching.boundaries.startTime - (xaxis.max - xaxis.min),
                        opts.caching.boundaries.endTime + (xaxis.max - xaxis.min)];
                }]
            }
        };
        
        /**
        * Bind the plot pan event
        */
        _flotContainer.bind('plotpan', function(event, plot){
            if (_txtLinkArea) _txtLinkArea.hide();
        });
        
        /**
        * Bind the plot zoom event
        */
        _flotContainer.bind('plotzoom', function(event, plot){
            if (_txtLinkArea) _txtLinkArea.hide();
        });
        
        var _addControls = function(){
            var all = $('<div id="settings" style="width:675px;margin-left:auto;margin-right:auto;margin-bottom:4px ;font-size:smaller"></div>').appendTo(_container);
            var snap = $('<div style="text-align:center"></div>').appendTo(all);
    
            _navGraph.buildSnapControls(snap, _options.boundaries.startingRange);
        }

        /**
        * Upates the graph title to show the current time range
        */
        var _updateTitle = function() {
            var axes = _ctrl.getUsedAxes(), startms, endms; 
            if (axes[0]) {
                startms = axes[0].min;
                endms = axes[0].max;
            }

            // check for no axis data
            if (!startms || !endms) return;

            var start = new Date(startms).toUTCString();
            var end = new Date(endms).toUTCString(); 
            start = start.substr(0, start.length -4);
            end = end.substr(0, end.length -4);
            var startDate = new Date(start);

            // less than of equal to a days difference
            if (endms - startms <= MS_1HOUR) {
                _title.html("<h3>" +
                    new Date(start).toString("h:mm tt ddd MMM d yyyy") +
                    "</h3>"
                );

            // when there is exactly a day difference - display only a day
            } else if (startms == endms - MS_1DAY && startDate.toString("ssmmHH") == "000000") {
                _title.html("<h3>" +
                    new Date(start).toString("ddd MMM d yyyy") +
                    "</h3>"
                );

            // exactly a week difference
            } else if (startms == endms - MS_1WEEK && startDate.is().sunday()) {
                _title.html("<h3>Week starting " +
                    new Date(start).toString("ddd MMM d yyyy") +
                    "</h3>"
                );

            // display the standard title
            } else {
                _title.html("<h3>" +
                    new Date(start).toString("ddd MMM d yyyy") +
                    " to " +
                    new Date(end).toString("ddd MMM d yyyy") +
                    "</h3>"
                );
            }
        };
        
        /**
        * Get the graph URL
        */
        var _getLink = function(){
            var axes = _ctrl.getGraphRange();
            var url = _options.pageUrl;
            
            // add axes options to the url
            url += "&use=1" +
            "&xmin=" +
            axes.xaxis.min +
            "&xmax=" +
            axes.xaxis.max +
            "&ymin=" +
            axes.yaxis.min +
            "&ymax=" +
            axes.yaxis.max +
            "&boxes=";
            
            // add the ticked boxes to the url
            for (var i = 0; i < _checkboxes.length; i++) {
                var checkbox = _checkboxes[i][0];
                
                if (checkbox.checked) 
                    url += checkbox.name + "_";
            }
            
            // remove the last divider
            if (url.substr(url.length - 1) == "_") 
                url = url.substr(0, url.length - 1);
            
            // output the url
            _txtLinkArea.val(url);
            _txtLinkArea.show();
        };
        
        _container.bind('plotmove', function(event, axes){
            _updateTitle();
        });
        
        var _initialise = function(){
            var style = { font: "80% Arial, Helvetica", fill: "rgb(0,0,0)" };

            _navGraph = $.navgraph(_flotContainer, _flotOptions, _options.xaxis.min, _options.xaxis.max);
            
            _ctrl = _navGraph.getController();
            
            if (_options.statName) 
                _ctrl.setSelected([_options.statName]);

            // draw the x label
            if (_options.xaxis.label)
                _ctrl.drawXaxisLabel(_options.xaxis.label, style, "bottom");
            
            // draw the y label
            if (_options.title.text)
                _ctrl.drawYaxisLabel(_options.yaxis.label, style, "left");
           
            _addControls();
            _navGraph.buildNavControls();
        };
        
        _initialise();
    }
    
    $.ampmeshtimeview = function(container, options) {
            return new AmpMeshTimeNavView($(container), options);
    };
})(jQuery);
