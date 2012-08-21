(function($) {
    function NavGraphController(flotContainer, flotOptions){
        var _thisNavGraphController = this;
        
        var _flot;
        var _flotContainer = flotContainer;
        var _flotOptions = flotOptions;
        var _flotSeries = [];
        var _selected;
        var _blockObj = {
            message: "No Data Available",
            css: {
                border: 'none', 
                padding: '15px', 
                backgroundColor: '#000', 
                '-webkit-border-radius': '10px', 
                '-moz-border-radius': '10px', 
                opacity: 0.5, 
                color: '#fff' 
            }
        };
        
        var _prevRange = {
            xaxis: {
                min: _flotOptions.xaxis.min,
                max: _flotOptions.xaxis.max
            }
        };
        
        this.mergeOptions = function(options, deep){
            _flotOptions = $.extend(deep, {}, _flotOptions, options);
        };
        
        this.setSeries = function(series){
            if (_selected && !_isData(series, _selected)) {
                _flotContainer.block(_blockObj);
            } 
            _flotSeries = series;
        };
        
        this.setSelected = function(selected){
            if (selected && _flotSeries.length > 0 && 
                !_isData(_flotSeries, selected)) {
                _flotContainer.block(_blockObj);
            } else {
                _selected = selected;
                _flotContainer.unblock();
            }
        }
        
        this.getGraphRange = function(){
            return _flot.getAxes();
        };
        
        this.drawSelected = function(selected){
            _flot.setDataSelected(_flotSeries, selected);
        };
        
        this.draw = function(){
            if (_flot) {
                if (!_selected || _isData(_flotSeries, _selected)) {
                    _flot.drawSelected(_flotSeries, _selected);
                
                // force flot to draw the axes
                } else if (_flotSeries.length > 0) {
                    _flot.setData([{ data: [ [0, 0], [1, 1] ] }]);
                    _flot.draw();
                }
                _saveRange();
            } else {
                _flot = $.plot(_flotContainer, _flotSeries, _flotOptions);
                _flot.drawSelected(_flotSeries, _selected);

                _flotOptions.caching.dataGrabber = _flot.getOptions().caching.dataGrabber;
                _flotContainer.trigger("plotredraw");
            }

            // initially pan to set the axes min and max
            _flot.pan({ left: 0, top: 0 });
            
            _flot.triggerRedrawOverlay();
        };
        
        this.invalidate = function(){
            _flot = undefined;
            _thisNavGraphController.draw();
        };
        
        /**
        * Rescale the y axis
        */
        this.autoScale = function(){
            return _flot.autoScale();
        }
        
        this.panRequest = function(min, max){
            var dataGrabber = _flot.getOptions().caching.dataGrabber;
            dataGrabber.moveTo(min, max);
            _triggerMove();
            return dataGrabber.getStatus();
        };
        
        this.zoomRequest = function(min, max){
            var dataGrabber = _flot.getOptions().caching.dataGrabber;
            dataGrabber.moveTo(min, max);
            _triggerMove();
            return dataGrabber.getStatus();
        };
        
        this.zoomOut = function(){
            _flot.zoomOut();
            _triggerMove();
        };
        
        this.zoom = function(){
            _flot.zoom();
            _triggerMove();
        };
        
        this.pan = function(offset){
            _flot.pan(offset);
            _triggerMove();
        };
        
        this.moveTo = function(args) {
            _flot.moveTo(args);
            if (args.xaxis) {
                _thisNavGraphController.zoomRequest(args.xaxis.min, args.xaxis.max);
            }
            _triggerMove();
        };

        this.nextRange = function(range) {
            return _flot.nextRange(range, 0);
        };

        this.prevRange = function(range) {
            return _flot.prevRange(range, 0);
        };

        this.now = function(range) {
            return _flot.now(range, 0);
        };

        this.snapTo = function(range) {
            return _flot.snapTo(range, 0);
        };

        this.drawXaxisLabel = function(label, style, position) {
            _flot.drawXaxisLabel(label, style, position);
        };
        
        this.drawYaxisLabel = function(label, style, position) {
            _flot.drawYaxisLabel(label, style, position);
        };

        this.getUsedAxes = function() {
            return _flot.getUsedAxes();
        };

        var _saveRange = function(){
            var axes = _flot.getAxes();
            
            _thisNavGraphController.mergeOptions({
                xaxis: {
                    min: axes.xaxis.min,
                    max: axes.xaxis.max
                },
                yaxis: {
                    min: axes.yaxis.min,
                    max: axes.yaxis.max
                }
            }, true);
        };

        var _isData = function(series, selected) {
            var noData, found = false;
            
            if (!series || !selected) {
		    return false;
	    }

            $.each(series, function(index, s) {
                $.each(selected, function(index, select) {
                    if (select == s.label) found = true;
                });
            });
            
            return found; 
        };
        
        var _triggerMove = function() {
            _flotContainer.trigger("plotmove", [_flot.getUsedAxes()]);
            _flot.triggerRedrawOverlay();
        };
        
        var _initialise = function(){
            _thisNavGraphController.invalidate();
        };
        
        _initialise();
    }
    
    $.navctrl = function(flotContainer, flotOptions) {
            return new NavGraphController($(flotContainer), flotOptions);
    };
})(jQuery);
