def self.build_last_value_tracker(plugin)
      if plugin.use_column_value && plugin.tracking_column_type == "numeric"
        # use this irrespective of the jdbc_default_timezone setting
        klass = NumericValueTracker
      else
        if plugin.jdbc_default_timezone.nil? || plugin.jdbc_default_timezone.empty?
          # no TZ stuff for Sequel, use Time
          klass = TimeValueTracker
        else
          # Sequel does timezone handling on DateTime only
          klass = DateTimeValueTracker
        end
      end

      handler = NullFileHandler.new(plugin.last_run_metadata_path)
      if plugin.record_last_run
        handler = FileHandler.new(plugin.last_run_metadata_path)
      end
      if plugin.clean_run
        handler.clean
      end

      instance = klass.new(handler)
    end


class DateTimeValueTracker < ValueTracking
    def get_initial
      @file_handler.read || DateTime.new(1970)
    end

    def set_value(value)
      @value = value
    end
  end

  class TimeValueTracker < ValueTracking
    def get_initial
      @file_handler.read || Time.utc
    end

    def set_value(value)
      if value.respond_to?(:to_time)
        @value = value.to_time
      else
        @value = DateTime.parse(value).to_time
      end
    end
  end