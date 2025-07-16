"use strict";
(self["webpackChunk"] = self["webpackChunk"] || []).push([["app"],{

/***/ "./assets/app.js":
/*!***********************!*\
  !*** ./assets/app.js ***!
  \***********************/
/***/ ((__unused_webpack_module, __webpack_exports__, __webpack_require__) => {

__webpack_require__.r(__webpack_exports__);
/* harmony import */ var _bootstrap_js__WEBPACK_IMPORTED_MODULE_0__ = __webpack_require__(/*! ./bootstrap.js */ "./assets/bootstrap.js");
/* harmony import */ var _styles_app_css__WEBPACK_IMPORTED_MODULE_1__ = __webpack_require__(/*! ./styles/app.css */ "./assets/styles/app.css");



/***/ }),

/***/ "./assets/bootstrap.js":
/*!*****************************!*\
  !*** ./assets/bootstrap.js ***!
  \*****************************/
/***/ ((__unused_webpack_module, __webpack_exports__, __webpack_require__) => {

__webpack_require__.r(__webpack_exports__);
/* harmony import */ var _symfony_stimulus_bridge__WEBPACK_IMPORTED_MODULE_0__ = __webpack_require__(/*! @symfony/stimulus-bridge */ "./node_modules/@symfony/stimulus-bridge/dist/index.js");
/* harmony import */ var _controllers_chart_simple_controller_js__WEBPACK_IMPORTED_MODULE_1__ = __webpack_require__(/*! ./controllers/chart_simple_controller.js */ "./assets/controllers/chart_simple_controller.js");

var app = (0,_symfony_stimulus_bridge__WEBPACK_IMPORTED_MODULE_0__.startStimulusApp)();

// Import and register our custom controllers

app.register('chart-simple', _controllers_chart_simple_controller_js__WEBPACK_IMPORTED_MODULE_1__["default"]);

/***/ }),

/***/ "./assets/controllers/chart_simple_controller.js":
/*!*******************************************************!*\
  !*** ./assets/controllers/chart_simple_controller.js ***!
  \*******************************************************/
/***/ ((__unused_webpack_module, __webpack_exports__, __webpack_require__) => {

__webpack_require__.r(__webpack_exports__);
/* harmony export */ __webpack_require__.d(__webpack_exports__, {
/* harmony export */   "default": () => (/* binding */ _default)
/* harmony export */ });
/* harmony import */ var core_js_modules_es_symbol_js__WEBPACK_IMPORTED_MODULE_0__ = __webpack_require__(/*! core-js/modules/es.symbol.js */ "./node_modules/core-js/modules/es.symbol.js");
/* harmony import */ var core_js_modules_es_symbol_js__WEBPACK_IMPORTED_MODULE_0___default = /*#__PURE__*/__webpack_require__.n(core_js_modules_es_symbol_js__WEBPACK_IMPORTED_MODULE_0__);
/* harmony import */ var core_js_modules_es_symbol_description_js__WEBPACK_IMPORTED_MODULE_1__ = __webpack_require__(/*! core-js/modules/es.symbol.description.js */ "./node_modules/core-js/modules/es.symbol.description.js");
/* harmony import */ var core_js_modules_es_symbol_description_js__WEBPACK_IMPORTED_MODULE_1___default = /*#__PURE__*/__webpack_require__.n(core_js_modules_es_symbol_description_js__WEBPACK_IMPORTED_MODULE_1__);
/* harmony import */ var core_js_modules_es_symbol_iterator_js__WEBPACK_IMPORTED_MODULE_2__ = __webpack_require__(/*! core-js/modules/es.symbol.iterator.js */ "./node_modules/core-js/modules/es.symbol.iterator.js");
/* harmony import */ var core_js_modules_es_symbol_iterator_js__WEBPACK_IMPORTED_MODULE_2___default = /*#__PURE__*/__webpack_require__.n(core_js_modules_es_symbol_iterator_js__WEBPACK_IMPORTED_MODULE_2__);
/* harmony import */ var core_js_modules_es_symbol_to_primitive_js__WEBPACK_IMPORTED_MODULE_3__ = __webpack_require__(/*! core-js/modules/es.symbol.to-primitive.js */ "./node_modules/core-js/modules/es.symbol.to-primitive.js");
/* harmony import */ var core_js_modules_es_symbol_to_primitive_js__WEBPACK_IMPORTED_MODULE_3___default = /*#__PURE__*/__webpack_require__.n(core_js_modules_es_symbol_to_primitive_js__WEBPACK_IMPORTED_MODULE_3__);
/* harmony import */ var core_js_modules_es_error_cause_js__WEBPACK_IMPORTED_MODULE_4__ = __webpack_require__(/*! core-js/modules/es.error.cause.js */ "./node_modules/core-js/modules/es.error.cause.js");
/* harmony import */ var core_js_modules_es_error_cause_js__WEBPACK_IMPORTED_MODULE_4___default = /*#__PURE__*/__webpack_require__.n(core_js_modules_es_error_cause_js__WEBPACK_IMPORTED_MODULE_4__);
/* harmony import */ var core_js_modules_es_error_to_string_js__WEBPACK_IMPORTED_MODULE_5__ = __webpack_require__(/*! core-js/modules/es.error.to-string.js */ "./node_modules/core-js/modules/es.error.to-string.js");
/* harmony import */ var core_js_modules_es_error_to_string_js__WEBPACK_IMPORTED_MODULE_5___default = /*#__PURE__*/__webpack_require__.n(core_js_modules_es_error_to_string_js__WEBPACK_IMPORTED_MODULE_5__);
/* harmony import */ var core_js_modules_es_array_find_index_js__WEBPACK_IMPORTED_MODULE_6__ = __webpack_require__(/*! core-js/modules/es.array.find-index.js */ "./node_modules/core-js/modules/es.array.find-index.js");
/* harmony import */ var core_js_modules_es_array_find_index_js__WEBPACK_IMPORTED_MODULE_6___default = /*#__PURE__*/__webpack_require__.n(core_js_modules_es_array_find_index_js__WEBPACK_IMPORTED_MODULE_6__);
/* harmony import */ var core_js_modules_es_array_for_each_js__WEBPACK_IMPORTED_MODULE_7__ = __webpack_require__(/*! core-js/modules/es.array.for-each.js */ "./node_modules/core-js/modules/es.array.for-each.js");
/* harmony import */ var core_js_modules_es_array_for_each_js__WEBPACK_IMPORTED_MODULE_7___default = /*#__PURE__*/__webpack_require__.n(core_js_modules_es_array_for_each_js__WEBPACK_IMPORTED_MODULE_7__);
/* harmony import */ var core_js_modules_es_array_iterator_js__WEBPACK_IMPORTED_MODULE_8__ = __webpack_require__(/*! core-js/modules/es.array.iterator.js */ "./node_modules/core-js/modules/es.array.iterator.js");
/* harmony import */ var core_js_modules_es_array_iterator_js__WEBPACK_IMPORTED_MODULE_8___default = /*#__PURE__*/__webpack_require__.n(core_js_modules_es_array_iterator_js__WEBPACK_IMPORTED_MODULE_8__);
/* harmony import */ var core_js_modules_es_date_to_primitive_js__WEBPACK_IMPORTED_MODULE_9__ = __webpack_require__(/*! core-js/modules/es.date.to-primitive.js */ "./node_modules/core-js/modules/es.date.to-primitive.js");
/* harmony import */ var core_js_modules_es_date_to_primitive_js__WEBPACK_IMPORTED_MODULE_9___default = /*#__PURE__*/__webpack_require__.n(core_js_modules_es_date_to_primitive_js__WEBPACK_IMPORTED_MODULE_9__);
/* harmony import */ var core_js_modules_es_function_bind_js__WEBPACK_IMPORTED_MODULE_10__ = __webpack_require__(/*! core-js/modules/es.function.bind.js */ "./node_modules/core-js/modules/es.function.bind.js");
/* harmony import */ var core_js_modules_es_function_bind_js__WEBPACK_IMPORTED_MODULE_10___default = /*#__PURE__*/__webpack_require__.n(core_js_modules_es_function_bind_js__WEBPACK_IMPORTED_MODULE_10__);
/* harmony import */ var core_js_modules_es_number_constructor_js__WEBPACK_IMPORTED_MODULE_11__ = __webpack_require__(/*! core-js/modules/es.number.constructor.js */ "./node_modules/core-js/modules/es.number.constructor.js");
/* harmony import */ var core_js_modules_es_number_constructor_js__WEBPACK_IMPORTED_MODULE_11___default = /*#__PURE__*/__webpack_require__.n(core_js_modules_es_number_constructor_js__WEBPACK_IMPORTED_MODULE_11__);
/* harmony import */ var core_js_modules_es_object_create_js__WEBPACK_IMPORTED_MODULE_12__ = __webpack_require__(/*! core-js/modules/es.object.create.js */ "./node_modules/core-js/modules/es.object.create.js");
/* harmony import */ var core_js_modules_es_object_create_js__WEBPACK_IMPORTED_MODULE_12___default = /*#__PURE__*/__webpack_require__.n(core_js_modules_es_object_create_js__WEBPACK_IMPORTED_MODULE_12__);
/* harmony import */ var core_js_modules_es_object_define_property_js__WEBPACK_IMPORTED_MODULE_13__ = __webpack_require__(/*! core-js/modules/es.object.define-property.js */ "./node_modules/core-js/modules/es.object.define-property.js");
/* harmony import */ var core_js_modules_es_object_define_property_js__WEBPACK_IMPORTED_MODULE_13___default = /*#__PURE__*/__webpack_require__.n(core_js_modules_es_object_define_property_js__WEBPACK_IMPORTED_MODULE_13__);
/* harmony import */ var core_js_modules_es_object_get_prototype_of_js__WEBPACK_IMPORTED_MODULE_14__ = __webpack_require__(/*! core-js/modules/es.object.get-prototype-of.js */ "./node_modules/core-js/modules/es.object.get-prototype-of.js");
/* harmony import */ var core_js_modules_es_object_get_prototype_of_js__WEBPACK_IMPORTED_MODULE_14___default = /*#__PURE__*/__webpack_require__.n(core_js_modules_es_object_get_prototype_of_js__WEBPACK_IMPORTED_MODULE_14__);
/* harmony import */ var core_js_modules_es_object_set_prototype_of_js__WEBPACK_IMPORTED_MODULE_15__ = __webpack_require__(/*! core-js/modules/es.object.set-prototype-of.js */ "./node_modules/core-js/modules/es.object.set-prototype-of.js");
/* harmony import */ var core_js_modules_es_object_set_prototype_of_js__WEBPACK_IMPORTED_MODULE_15___default = /*#__PURE__*/__webpack_require__.n(core_js_modules_es_object_set_prototype_of_js__WEBPACK_IMPORTED_MODULE_15__);
/* harmony import */ var core_js_modules_es_object_to_string_js__WEBPACK_IMPORTED_MODULE_16__ = __webpack_require__(/*! core-js/modules/es.object.to-string.js */ "./node_modules/core-js/modules/es.object.to-string.js");
/* harmony import */ var core_js_modules_es_object_to_string_js__WEBPACK_IMPORTED_MODULE_16___default = /*#__PURE__*/__webpack_require__.n(core_js_modules_es_object_to_string_js__WEBPACK_IMPORTED_MODULE_16__);
/* harmony import */ var core_js_modules_es_reflect_construct_js__WEBPACK_IMPORTED_MODULE_17__ = __webpack_require__(/*! core-js/modules/es.reflect.construct.js */ "./node_modules/core-js/modules/es.reflect.construct.js");
/* harmony import */ var core_js_modules_es_reflect_construct_js__WEBPACK_IMPORTED_MODULE_17___default = /*#__PURE__*/__webpack_require__.n(core_js_modules_es_reflect_construct_js__WEBPACK_IMPORTED_MODULE_17__);
/* harmony import */ var core_js_modules_es_string_iterator_js__WEBPACK_IMPORTED_MODULE_18__ = __webpack_require__(/*! core-js/modules/es.string.iterator.js */ "./node_modules/core-js/modules/es.string.iterator.js");
/* harmony import */ var core_js_modules_es_string_iterator_js__WEBPACK_IMPORTED_MODULE_18___default = /*#__PURE__*/__webpack_require__.n(core_js_modules_es_string_iterator_js__WEBPACK_IMPORTED_MODULE_18__);
/* harmony import */ var core_js_modules_esnext_iterator_constructor_js__WEBPACK_IMPORTED_MODULE_19__ = __webpack_require__(/*! core-js/modules/esnext.iterator.constructor.js */ "./node_modules/core-js/modules/esnext.iterator.constructor.js");
/* harmony import */ var core_js_modules_esnext_iterator_constructor_js__WEBPACK_IMPORTED_MODULE_19___default = /*#__PURE__*/__webpack_require__.n(core_js_modules_esnext_iterator_constructor_js__WEBPACK_IMPORTED_MODULE_19__);
/* harmony import */ var core_js_modules_esnext_iterator_for_each_js__WEBPACK_IMPORTED_MODULE_20__ = __webpack_require__(/*! core-js/modules/esnext.iterator.for-each.js */ "./node_modules/core-js/modules/esnext.iterator.for-each.js");
/* harmony import */ var core_js_modules_esnext_iterator_for_each_js__WEBPACK_IMPORTED_MODULE_20___default = /*#__PURE__*/__webpack_require__.n(core_js_modules_esnext_iterator_for_each_js__WEBPACK_IMPORTED_MODULE_20__);
/* harmony import */ var core_js_modules_web_dom_collections_for_each_js__WEBPACK_IMPORTED_MODULE_21__ = __webpack_require__(/*! core-js/modules/web.dom-collections.for-each.js */ "./node_modules/core-js/modules/web.dom-collections.for-each.js");
/* harmony import */ var core_js_modules_web_dom_collections_for_each_js__WEBPACK_IMPORTED_MODULE_21___default = /*#__PURE__*/__webpack_require__.n(core_js_modules_web_dom_collections_for_each_js__WEBPACK_IMPORTED_MODULE_21__);
/* harmony import */ var core_js_modules_web_dom_collections_iterator_js__WEBPACK_IMPORTED_MODULE_22__ = __webpack_require__(/*! core-js/modules/web.dom-collections.iterator.js */ "./node_modules/core-js/modules/web.dom-collections.iterator.js");
/* harmony import */ var core_js_modules_web_dom_collections_iterator_js__WEBPACK_IMPORTED_MODULE_22___default = /*#__PURE__*/__webpack_require__.n(core_js_modules_web_dom_collections_iterator_js__WEBPACK_IMPORTED_MODULE_22__);
/* harmony import */ var core_js_modules_web_timers_js__WEBPACK_IMPORTED_MODULE_23__ = __webpack_require__(/*! core-js/modules/web.timers.js */ "./node_modules/core-js/modules/web.timers.js");
/* harmony import */ var core_js_modules_web_timers_js__WEBPACK_IMPORTED_MODULE_23___default = /*#__PURE__*/__webpack_require__.n(core_js_modules_web_timers_js__WEBPACK_IMPORTED_MODULE_23__);
/* harmony import */ var _hotwired_stimulus__WEBPACK_IMPORTED_MODULE_24__ = __webpack_require__(/*! @hotwired/stimulus */ "./node_modules/@hotwired/stimulus/dist/stimulus.js");
function _typeof(o) { "@babel/helpers - typeof"; return _typeof = "function" == typeof Symbol && "symbol" == typeof Symbol.iterator ? function (o) { return typeof o; } : function (o) { return o && "function" == typeof Symbol && o.constructor === Symbol && o !== Symbol.prototype ? "symbol" : typeof o; }, _typeof(o); }
























function _classCallCheck(a, n) { if (!(a instanceof n)) throw new TypeError("Cannot call a class as a function"); }
function _defineProperties(e, r) { for (var t = 0; t < r.length; t++) { var o = r[t]; o.enumerable = o.enumerable || !1, o.configurable = !0, "value" in o && (o.writable = !0), Object.defineProperty(e, _toPropertyKey(o.key), o); } }
function _createClass(e, r, t) { return r && _defineProperties(e.prototype, r), t && _defineProperties(e, t), Object.defineProperty(e, "prototype", { writable: !1 }), e; }
function _toPropertyKey(t) { var i = _toPrimitive(t, "string"); return "symbol" == _typeof(i) ? i : i + ""; }
function _toPrimitive(t, r) { if ("object" != _typeof(t) || !t) return t; var e = t[Symbol.toPrimitive]; if (void 0 !== e) { var i = e.call(t, r || "default"); if ("object" != _typeof(i)) return i; throw new TypeError("@@toPrimitive must return a primitive value."); } return ("string" === r ? String : Number)(t); }
function _callSuper(t, o, e) { return o = _getPrototypeOf(o), _possibleConstructorReturn(t, _isNativeReflectConstruct() ? Reflect.construct(o, e || [], _getPrototypeOf(t).constructor) : o.apply(t, e)); }
function _possibleConstructorReturn(t, e) { if (e && ("object" == _typeof(e) || "function" == typeof e)) return e; if (void 0 !== e) throw new TypeError("Derived constructors may only return object or undefined"); return _assertThisInitialized(t); }
function _assertThisInitialized(e) { if (void 0 === e) throw new ReferenceError("this hasn't been initialised - super() hasn't been called"); return e; }
function _isNativeReflectConstruct() { try { var t = !Boolean.prototype.valueOf.call(Reflect.construct(Boolean, [], function () {})); } catch (t) {} return (_isNativeReflectConstruct = function _isNativeReflectConstruct() { return !!t; })(); }
function _getPrototypeOf(t) { return _getPrototypeOf = Object.setPrototypeOf ? Object.getPrototypeOf.bind() : function (t) { return t.__proto__ || Object.getPrototypeOf(t); }, _getPrototypeOf(t); }
function _inherits(t, e) { if ("function" != typeof e && null !== e) throw new TypeError("Super expression must either be null or a function"); t.prototype = Object.create(e && e.prototype, { constructor: { value: t, writable: !0, configurable: !0 } }), Object.defineProperty(t, "prototype", { writable: !1 }), e && _setPrototypeOf(t, e); }
function _setPrototypeOf(t, e) { return _setPrototypeOf = Object.setPrototypeOf ? Object.setPrototypeOf.bind() : function (t, e) { return t.__proto__ = e, t; }, _setPrototypeOf(t, e); }

var _default = /*#__PURE__*/function (_Controller) {
  function _default() {
    _classCallCheck(this, _default);
    return _callSuper(this, _default, arguments);
  }
  _inherits(_default, _Controller);
  return _createClass(_default, [{
    key: "connect",
    value: function connect() {
      var _this = this;
      console.log('Chart simple controller connected');
      this.chart = null;

      // Écouter l'événement Symfony UX Chartjs
      this.element.addEventListener('chartjs:connect', function (event) {
        console.log('Chart.js connect event received', event.detail);
        _this.chart = event.detail.chart;
        _this.setupFeatures();
      });

      // Fallback: essayer de trouver le graphique après un délai
      setTimeout(function () {
        if (!_this.chart) {
          _this.findExistingChart();
        }
      }, 1000);
    }
  }, {
    key: "findExistingChart",
    value: function findExistingChart() {
      console.log('Looking for existing chart...');
      var canvas = this.element.querySelector('canvas');
      if (canvas && window.Chart) {
        var chart = window.Chart.getChart(canvas);
        if (chart) {
          console.log('Found existing chart:', chart);
          this.chart = chart;
          this.setupFeatures();
        } else {
          console.log('No chart found on canvas');
        }
      }
    }
  }, {
    key: "setupFeatures",
    value: function setupFeatures() {
      console.log('Setting up chart features...');
      this.initializeCheckboxes();
      this.setupEventListeners();
    }
  }, {
    key: "initializeCheckboxes",
    value: function initializeCheckboxes() {
      var _this2 = this;
      if (!this.chart) {
        console.log('No chart available for checkbox initialization');
        return;
      }
      console.log('Initializing checkboxes...');
      var checkboxes = document.querySelectorAll('#componentCheckboxes input[type="checkbox"]');
      checkboxes.forEach(function (checkbox) {
        var componentName = checkbox.value;
        var datasetIndex = _this2.chart.data.datasets.findIndex(function (dataset) {
          return dataset.label === componentName;
        });
        if (datasetIndex !== -1) {
          var isVisible = _this2.chart.isDatasetVisible(datasetIndex);
          checkbox.checked = isVisible;
          console.log("Checkbox for \"".concat(componentName, "\" set to:"), isVisible);
        } else {
          console.log("Dataset not found for component: ".concat(componentName));
        }
      });
    }
  }, {
    key: "setupEventListeners",
    value: function setupEventListeners() {
      var _this3 = this;
      console.log('Setting up event listeners...');

      // Type de graphique
      var chartTypeSelect = document.getElementById('chartType');
      if (chartTypeSelect) {
        chartTypeSelect.addEventListener('change', function (event) {
          _this3.changeChartType(event.target.value);
        });
      }

      // Checkboxes pour les composants
      document.addEventListener('change', function (event) {
        if (event.target.matches('#componentCheckboxes input[type="checkbox"]')) {
          _this3.toggleDatasetVisibility(event.target.value, event.target.checked);
        }
      });
    }
  }, {
    key: "changeChartType",
    value: function changeChartType(newType) {
      if (!this.chart) {
        console.log('No chart available for type change');
        return;
      }
      console.log('Changing chart type to:', newType);
      try {
        this.chart.config.type = newType;
        this.chart.update();
        console.log('Chart type changed successfully');
      } catch (error) {
        console.error('Error changing chart type:', error);
      }
    }
  }, {
    key: "toggleDatasetVisibility",
    value: function toggleDatasetVisibility(componentName, isVisible) {
      if (!this.chart) {
        console.log('No chart available for dataset toggle');
        return;
      }
      console.log('Toggling dataset:', componentName, 'to', isVisible);
      try {
        var datasetIndex = this.chart.data.datasets.findIndex(function (dataset) {
          return dataset.label === componentName;
        });
        if (datasetIndex !== -1) {
          this.chart.setDatasetVisibility(datasetIndex, isVisible);
          this.chart.update();
          console.log('Dataset visibility toggled successfully');
        } else {
          console.warn('Dataset not found:', componentName);
        }
      } catch (error) {
        console.error('Error toggling dataset:', error);
      }
    }
  }, {
    key: "disconnect",
    value: function disconnect() {
      console.log('Chart simple controller disconnected');
      this.chart = null;
    }
  }]);
}(_hotwired_stimulus__WEBPACK_IMPORTED_MODULE_24__.Controller);


/***/ }),

/***/ "./assets/styles/app.css":
/*!*******************************!*\
  !*** ./assets/styles/app.css ***!
  \*******************************/
/***/ ((__unused_webpack_module, __webpack_exports__, __webpack_require__) => {

__webpack_require__.r(__webpack_exports__);
// extracted by mini-css-extract-plugin


/***/ }),

/***/ "./node_modules/@symfony/stimulus-bridge/dist/webpack/loader.js!./assets/controllers.json":
/*!************************************************************************************************!*\
  !*** ./node_modules/@symfony/stimulus-bridge/dist/webpack/loader.js!./assets/controllers.json ***!
  \************************************************************************************************/
/***/ ((__unused_webpack_module, __webpack_exports__, __webpack_require__) => {

__webpack_require__.r(__webpack_exports__);
/* harmony export */ __webpack_require__.d(__webpack_exports__, {
/* harmony export */   "default": () => (__WEBPACK_DEFAULT_EXPORT__)
/* harmony export */ });
/* harmony default export */ const __WEBPACK_DEFAULT_EXPORT__ = ({
});

/***/ })

},
/******/ __webpack_require__ => { // webpackRuntimeModules
/******/ var __webpack_exec__ = (moduleId) => (__webpack_require__(__webpack_require__.s = moduleId))
/******/ __webpack_require__.O(0, ["vendors-node_modules_symfony_stimulus-bridge_dist_index_js-node_modules_core-js_modules_es_ar-9b0951"], () => (__webpack_exec__("./assets/app.js")));
/******/ var __webpack_exports__ = __webpack_require__.O();
/******/ }
]);
//# sourceMappingURL=data:application/json;charset=utf-8;base64,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QUFDO1FBQ3BEO01BQ0o7TUFFQUQsT0FBTyxDQUFDQyxHQUFHLENBQUMsbUJBQW1CLEVBQUVzQixhQUFhLEVBQUUsSUFBSSxFQUFFTyxTQUFTLENBQUM7TUFFaEUsSUFBSTtRQUNBLElBQU1OLFlBQVksR0FBRyxJQUFJLENBQUN0QixLQUFLLENBQUN1QixJQUFJLENBQUNDLFFBQVEsQ0FBQ0MsU0FBUyxDQUFDLFVBQUFDLE9BQU87VUFBQSxPQUMzREEsT0FBTyxDQUFDQyxLQUFLLEtBQUtOLGFBQWE7UUFBQSxDQUNuQyxDQUFDO1FBRUQsSUFBSUMsWUFBWSxLQUFLLENBQUMsQ0FBQyxFQUFFO1VBQ3JCLElBQUksQ0FBQ3RCLEtBQUssQ0FBQzRDLG9CQUFvQixDQUFDdEIsWUFBWSxFQUFFTSxTQUFTLENBQUM7VUFDeEQsSUFBSSxDQUFDNUIsS0FBSyxDQUFDMEMsTUFBTSxDQUFDLENBQUM7VUFDbkI1QyxPQUFPLENBQUNDLEdBQUcsQ0FBQyx5Q0FBeUMsQ0FBQztRQUMxRCxDQUFDLE1BQU07VUFDSEQsT0FBTyxDQUFDK0MsSUFBSSxDQUFDLG9CQUFvQixFQUFFeEIsYUFBYSxDQUFDO1FBQ3JEO01BQ0osQ0FBQyxDQUFDLE9BQU9zQixLQUFLLEVBQUU7UUFDWjdDLE9BQU8sQ0FBQzZDLEtBQUssQ0FBQyx5QkFBeUIsRUFBRUEsS0FBSyxDQUFDO01BQ25EO0lBQ0o7RUFBQztJQUFBakQsR0FBQTtJQUFBQyxLQUFBLEVBRUQsU0FBQW1ELFVBQVVBLENBQUEsRUFBRztNQUNUaEQsT0FBTyxDQUFDQyxHQUFHLENBQUMsc0NBQXNDLENBQUM7TUFDbkQsSUFBSSxDQUFDQyxLQUFLLEdBQUcsSUFBSTtJQUNyQjtFQUFDO0FBQUEsRUFqSXdCZCwyREFBVTs7Ozs7Ozs7Ozs7O0FDRnZDOzs7Ozs7Ozs7Ozs7Ozs7QUNBQSxpRUFBZTtBQUNmLENBQUMsRSIsInNvdXJjZXMiOlsid2VicGFjazovLy8uL2Fzc2V0cy9hcHAuanMiLCJ3ZWJwYWNrOi8vLy4vYXNzZXRzL2Jvb3RzdHJhcC5qcyIsIndlYnBhY2s6Ly8vLi9hc3NldHMvY29udHJvbGxlcnMvY2hhcnRfc2ltcGxlX2NvbnRyb2xsZXIuanMiLCJ3ZWJwYWNrOi8vLy4vYXNzZXRzL3N0eWxlcy9hcHAuY3NzPzNmYmEiLCJ3ZWJwYWNrOi8vLy4vYXNzZXRzL2NvbnRyb2xsZXJzLmpzb24iXSwic291cmNlc0NvbnRlbnQiOlsiaW1wb3J0ICcuL2Jvb3RzdHJhcC5qcyc7XG5pbXBvcnQgJy4vc3R5bGVzL2FwcC5jc3MnO1xuIiwiaW1wb3J0IHsgc3RhcnRTdGltdWx1c0FwcCB9IGZyb20gJ0BzeW1mb255L3N0aW11bHVzLWJyaWRnZSc7XG5cbmNvbnN0IGFwcCA9IHN0YXJ0U3RpbXVsdXNBcHAoKTtcblxuLy8gSW1wb3J0IGFuZCByZWdpc3RlciBvdXIgY3VzdG9tIGNvbnRyb2xsZXJzXG5pbXBvcnQgQ2hhcnRTaW1wbGVDb250cm9sbGVyIGZyb20gJy4vY29udHJvbGxlcnMvY2hhcnRfc2ltcGxlX2NvbnRyb2xsZXIuanMnO1xuYXBwLnJlZ2lzdGVyKCdjaGFydC1zaW1wbGUnLCBDaGFydFNpbXBsZUNvbnRyb2xsZXIpO1xuIiwiaW1wb3J0IHsgQ29udHJvbGxlciB9IGZyb20gJ0Bob3R3aXJlZC9zdGltdWx1cyc7XG5cbmV4cG9ydCBkZWZhdWx0IGNsYXNzIGV4dGVuZHMgQ29udHJvbGxlciB7XG4gICAgY29ubmVjdCgpIHtcbiAgICAgICAgY29uc29sZS5sb2coJ0NoYXJ0IHNpbXBsZSBjb250cm9sbGVyIGNvbm5lY3RlZCcpO1xuICAgICAgICB0aGlzLmNoYXJ0ID0gbnVsbDtcbiAgICAgICAgXG4gICAgICAgIC8vIMOJY291dGVyIGwnw6l2w6luZW1lbnQgU3ltZm9ueSBVWCBDaGFydGpzXG4gICAgICAgIHRoaXMuZWxlbWVudC5hZGRFdmVudExpc3RlbmVyKCdjaGFydGpzOmNvbm5lY3QnLCAoZXZlbnQpID0+IHtcbiAgICAgICAgICAgIGNvbnNvbGUubG9nKCdDaGFydC5qcyBjb25uZWN0IGV2ZW50IHJlY2VpdmVkJywgZXZlbnQuZGV0YWlsKTtcbiAgICAgICAgICAgIHRoaXMuY2hhcnQgPSBldmVudC5kZXRhaWwuY2hhcnQ7XG4gICAgICAgICAgICB0aGlzLnNldHVwRmVhdHVyZXMoKTtcbiAgICAgICAgfSk7XG4gICAgICAgIFxuICAgICAgICAvLyBGYWxsYmFjazogZXNzYXllciBkZSB0cm91dmVyIGxlIGdyYXBoaXF1ZSBhcHLDqHMgdW4gZMOpbGFpXG4gICAgICAgIHNldFRpbWVvdXQoKCkgPT4ge1xuICAgICAgICAgICAgaWYgKCF0aGlzLmNoYXJ0KSB7XG4gICAgICAgICAgICAgICAgdGhpcy5maW5kRXhpc3RpbmdDaGFydCgpO1xuICAgICAgICAgICAgfVxuICAgICAgICB9LCAxMDAwKTtcbiAgICB9XG5cbiAgICBmaW5kRXhpc3RpbmdDaGFydCgpIHtcbiAgICAgICAgY29uc29sZS5sb2coJ0xvb2tpbmcgZm9yIGV4aXN0aW5nIGNoYXJ0Li4uJyk7XG4gICAgICAgIGNvbnN0IGNhbnZhcyA9IHRoaXMuZWxlbWVudC5xdWVyeVNlbGVjdG9yKCdjYW52YXMnKTtcbiAgICAgICAgaWYgKGNhbnZhcyAmJiB3aW5kb3cuQ2hhcnQpIHtcbiAgICAgICAgICAgIGNvbnN0IGNoYXJ0ID0gd2luZG93LkNoYXJ0LmdldENoYXJ0KGNhbnZhcyk7XG4gICAgICAgICAgICBpZiAoY2hhcnQpIHtcbiAgICAgICAgICAgICAgICBjb25zb2xlLmxvZygnRm91bmQgZXhpc3RpbmcgY2hhcnQ6JywgY2hhcnQpO1xuICAgICAgICAgICAgICAgIHRoaXMuY2hhcnQgPSBjaGFydDtcbiAgICAgICAgICAgICAgICB0aGlzLnNldHVwRmVhdHVyZXMoKTtcbiAgICAgICAgICAgIH0gZWxzZSB7XG4gICAgICAgICAgICAgICAgY29uc29sZS5sb2coJ05vIGNoYXJ0IGZvdW5kIG9uIGNhbnZhcycpO1xuICAgICAgICAgICAgfVxuICAgICAgICB9XG4gICAgfVxuXG4gICAgc2V0dXBGZWF0dXJlcygpIHtcbiAgICAgICAgY29uc29sZS5sb2coJ1NldHRpbmcgdXAgY2hhcnQgZmVhdHVyZXMuLi4nKTtcbiAgICAgICAgdGhpcy5pbml0aWFsaXplQ2hlY2tib3hlcygpO1xuICAgICAgICB0aGlzLnNldHVwRXZlbnRMaXN0ZW5lcnMoKTtcbiAgICB9XG5cbiAgICBpbml0aWFsaXplQ2hlY2tib3hlcygpIHtcbiAgICAgICAgaWYgKCF0aGlzLmNoYXJ0KSB7XG4gICAgICAgICAgICBjb25zb2xlLmxvZygnTm8gY2hhcnQgYXZhaWxhYmxlIGZvciBjaGVja2JveCBpbml0aWFsaXphdGlvbicpO1xuICAgICAgICAgICAgcmV0dXJuO1xuICAgICAgICB9XG5cbiAgICAgICAgY29uc29sZS5sb2coJ0luaXRpYWxpemluZyBjaGVja2JveGVzLi4uJyk7XG4gICAgICAgIGNvbnN0IGNoZWNrYm94ZXMgPSBkb2N1bWVudC5xdWVyeVNlbGVjdG9yQWxsKCcjY29tcG9uZW50Q2hlY2tib3hlcyBpbnB1dFt0eXBlPVwiY2hlY2tib3hcIl0nKTtcbiAgICAgICAgXG4gICAgICAgIGNoZWNrYm94ZXMuZm9yRWFjaCgoY2hlY2tib3gpID0+IHtcbiAgICAgICAgICAgIGNvbnN0IGNvbXBvbmVudE5hbWUgPSBjaGVja2JveC52YWx1ZTtcbiAgICAgICAgICAgIGNvbnN0IGRhdGFzZXRJbmRleCA9IHRoaXMuY2hhcnQuZGF0YS5kYXRhc2V0cy5maW5kSW5kZXgoZGF0YXNldCA9PiBcbiAgICAgICAgICAgICAgICBkYXRhc2V0LmxhYmVsID09PSBjb21wb25lbnROYW1lXG4gICAgICAgICAgICApO1xuXG4gICAgICAgICAgICBpZiAoZGF0YXNldEluZGV4ICE9PSAtMSkge1xuICAgICAgICAgICAgICAgIGNvbnN0IGlzVmlzaWJsZSA9IHRoaXMuY2hhcnQuaXNEYXRhc2V0VmlzaWJsZShkYXRhc2V0SW5kZXgpO1xuICAgICAgICAgICAgICAgIGNoZWNrYm94LmNoZWNrZWQgPSBpc1Zpc2libGU7XG4gICAgICAgICAgICAgICAgY29uc29sZS5sb2coYENoZWNrYm94IGZvciBcIiR7Y29tcG9uZW50TmFtZX1cIiBzZXQgdG86YCwgaXNWaXNpYmxlKTtcbiAgICAgICAgICAgIH0gZWxzZSB7XG4gICAgICAgICAgICAgICAgY29uc29sZS5sb2coYERhdGFzZXQgbm90IGZvdW5kIGZvciBjb21wb25lbnQ6ICR7Y29tcG9uZW50TmFtZX1gKTtcbiAgICAgICAgICAgIH1cbiAgICAgICAgfSk7XG4gICAgfVxuXG4gICAgc2V0dXBFdmVudExpc3RlbmVycygpIHtcbiAgICAgICAgY29uc29sZS5sb2coJ1NldHRpbmcgdXAgZXZlbnQgbGlzdGVuZXJzLi4uJyk7XG5cbiAgICAgICAgLy8gVHlwZSBkZSBncmFwaGlxdWVcbiAgICAgICAgY29uc3QgY2hhcnRUeXBlU2VsZWN0ID0gZG9jdW1lbnQuZ2V0RWxlbWVudEJ5SWQoJ2NoYXJ0VHlwZScpO1xuICAgICAgICBpZiAoY2hhcnRUeXBlU2VsZWN0KSB7XG4gICAgICAgICAgICBjaGFydFR5cGVTZWxlY3QuYWRkRXZlbnRMaXN0ZW5lcignY2hhbmdlJywgKGV2ZW50KSA9PiB7XG4gICAgICAgICAgICAgICAgdGhpcy5jaGFuZ2VDaGFydFR5cGUoZXZlbnQudGFyZ2V0LnZhbHVlKTtcbiAgICAgICAgICAgIH0pO1xuICAgICAgICB9XG5cbiAgICAgICAgLy8gQ2hlY2tib3hlcyBwb3VyIGxlcyBjb21wb3NhbnRzXG4gICAgICAgIGRvY3VtZW50LmFkZEV2ZW50TGlzdGVuZXIoJ2NoYW5nZScsIChldmVudCkgPT4ge1xuICAgICAgICAgICAgaWYgKGV2ZW50LnRhcmdldC5tYXRjaGVzKCcjY29tcG9uZW50Q2hlY2tib3hlcyBpbnB1dFt0eXBlPVwiY2hlY2tib3hcIl0nKSkge1xuICAgICAgICAgICAgICAgIHRoaXMudG9nZ2xlRGF0YXNldFZpc2liaWxpdHkoZXZlbnQudGFyZ2V0LnZhbHVlLCBldmVudC50YXJnZXQuY2hlY2tlZCk7XG4gICAgICAgICAgICB9XG4gICAgICAgIH0pO1xuICAgIH1cblxuICAgIGNoYW5nZUNoYXJ0VHlwZShuZXdUeXBlKSB7XG4gICAgICAgIGlmICghdGhpcy5jaGFydCkge1xuICAgICAgICAgICAgY29uc29sZS5sb2coJ05vIGNoYXJ0IGF2YWlsYWJsZSBmb3IgdHlwZSBjaGFuZ2UnKTtcbiAgICAgICAgICAgIHJldHVybjtcbiAgICAgICAgfVxuXG4gICAgICAgIGNvbnNvbGUubG9nKCdDaGFuZ2luZyBjaGFydCB0eXBlIHRvOicsIG5ld1R5cGUpO1xuICAgICAgICB0cnkge1xuICAgICAgICAgICAgdGhpcy5jaGFydC5jb25maWcudHlwZSA9IG5ld1R5cGU7XG4gICAgICAgICAgICB0aGlzLmNoYXJ0LnVwZGF0ZSgpO1xuICAgICAgICAgICAgY29uc29sZS5sb2coJ0NoYXJ0IHR5cGUgY2hhbmdlZCBzdWNjZXNzZnVsbHknKTtcbiAgICAgICAgfSBjYXRjaCAoZXJyb3IpIHtcbiAgICAgICAgICAgIGNvbnNvbGUuZXJyb3IoJ0Vycm9yIGNoYW5naW5nIGNoYXJ0IHR5cGU6JywgZXJyb3IpO1xuICAgICAgICB9XG4gICAgfVxuXG4gICAgdG9nZ2xlRGF0YXNldFZpc2liaWxpdHkoY29tcG9uZW50TmFtZSwgaXNWaXNpYmxlKSB7XG4gICAgICAgIGlmICghdGhpcy5jaGFydCkge1xuICAgICAgICAgICAgY29uc29sZS5sb2coJ05vIGNoYXJ0IGF2YWlsYWJsZSBmb3IgZGF0YXNldCB0b2dnbGUnKTtcbiAgICAgICAgICAgIHJldHVybjtcbiAgICAgICAgfVxuXG4gICAgICAgIGNvbnNvbGUubG9nKCdUb2dnbGluZyBkYXRhc2V0OicsIGNvbXBvbmVudE5hbWUsICd0bycsIGlzVmlzaWJsZSk7XG4gICAgICAgIFxuICAgICAgICB0cnkge1xuICAgICAgICAgICAgY29uc3QgZGF0YXNldEluZGV4ID0gdGhpcy5jaGFydC5kYXRhLmRhdGFzZXRzLmZpbmRJbmRleChkYXRhc2V0ID0+IFxuICAgICAgICAgICAgICAgIGRhdGFzZXQubGFiZWwgPT09IGNvbXBvbmVudE5hbWVcbiAgICAgICAgICAgICk7XG5cbiAgICAgICAgICAgIGlmIChkYXRhc2V0SW5kZXggIT09IC0xKSB7XG4gICAgICAgICAgICAgICAgdGhpcy5jaGFydC5zZXREYXRhc2V0VmlzaWJpbGl0eShkYXRhc2V0SW5kZXgsIGlzVmlzaWJsZSk7XG4gICAgICAgICAgICAgICAgdGhpcy5jaGFydC51cGRhdGUoKTtcbiAgICAgICAgICAgICAgICBjb25zb2xlLmxvZygnRGF0YXNldCB2aXNpYmlsaXR5IHRvZ2dsZWQgc3VjY2Vzc2Z1bGx5Jyk7XG4gICAgICAgICAgICB9IGVsc2Uge1xuICAgICAgICAgICAgICAgIGNvbnNvbGUud2FybignRGF0YXNldCBub3QgZm91bmQ6JywgY29tcG9uZW50TmFtZSk7XG4gICAgICAgICAgICB9XG4gICAgICAgIH0gY2F0Y2ggKGVycm9yKSB7XG4gICAgICAgICAgICBjb25zb2xlLmVycm9yKCdFcnJvciB0b2dnbGluZyBkYXRhc2V0OicsIGVycm9yKTtcbiAgICAgICAgfVxuICAgIH1cblxuICAgIGRpc2Nvbm5lY3QoKSB7XG4gICAgICAgIGNvbnNvbGUubG9nKCdDaGFydCBzaW1wbGUgY29udHJvbGxlciBkaXNjb25uZWN0ZWQnKTtcbiAgICAgICAgdGhpcy5jaGFydCA9IG51bGw7XG4gICAgfVxufSIsIi8vIGV4dHJhY3RlZCBieSBtaW5pLWNzcy1leHRyYWN0LXBsdWdpblxuZXhwb3J0IHt9OyIsImV4cG9ydCBkZWZhdWx0IHtcbn07Il0sIm5hbWVzIjpbInN0YXJ0U3RpbXVsdXNBcHAiLCJhcHAiLCJDaGFydFNpbXBsZUNvbnRyb2xsZXIiLCJyZWdpc3RlciIsIkNvbnRyb2xsZXIiLCJfZGVmYXVsdCIsIl9Db250cm9sbGVyIiwiX2NsYXNzQ2FsbENoZWNrIiwiX2NhbGxTdXBlciIsImFyZ3VtZW50cyIsIl9pbmhlcml0cyIsIl9jcmVhdGVDbGFzcyIsImtleSIsInZhbHVlIiwiY29ubmVjdCIsIl90aGlzIiwiY29uc29sZSIsImxvZyIsImNoYXJ0IiwiZWxlbWVudCIsImFkZEV2ZW50TGlzdGVuZXIiLCJldmVudCIsImRldGFpbCIsInNldHVwRmVhdHVyZXMiLCJzZXRUaW1lb3V0IiwiZmluZEV4aXN0aW5nQ2hhcnQiLCJjYW52YXMiLCJxdWVyeVNlbGVjdG9yIiwid2luZG93IiwiQ2hhcnQiLCJnZXRDaGFydCIsImluaXRpYWxpemVDaGVja2JveGVzIiwic2V0dXBFdmVudExpc3RlbmVycyIsIl90aGlzMiIsImNoZWNrYm94ZXMiLCJkb2N1bWVudCIsInF1ZXJ5U2VsZWN0b3JBbGwiLCJmb3JFYWNoIiwiY2hlY2tib3giLCJjb21wb25lbnROYW1lIiwiZGF0YXNldEluZGV4IiwiZGF0YSIsImRhdGFzZXRzIiwiZmluZEluZGV4IiwiZGF0YXNldCIsImxhYmVsIiwiaXNWaXNpYmxlIiwiaXNEYXRhc2V0VmlzaWJsZSIsImNoZWNrZWQiLCJjb25jYXQiLCJfdGhpczMiLCJjaGFydFR5cGVTZWxlY3QiLCJnZXRFbGVtZW50QnlJZCIsImNoYW5nZUNoYXJ0VHlwZSIsInRhcmdldCIsIm1hdGNoZXMiLCJ0b2dnbGVEYXRhc2V0VmlzaWJpbGl0eSIsIm5ld1R5cGUiLCJjb25maWciLCJ0eXBlIiwidXBkYXRlIiwiZXJyb3IiLCJzZXREYXRhc2V0VmlzaWJpbGl0eSIsIndhcm4iLCJkaXNjb25uZWN0IiwiZGVmYXVsdCJdLCJzb3VyY2VSb290IjoiIn0=