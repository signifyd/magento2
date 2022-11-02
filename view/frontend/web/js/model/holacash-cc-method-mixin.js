define(function () {
  "use strict";
  var mixin = {
    getData: function () {
      var returnInformation = this._super();

      if (typeof window.holacashCardObject !== "undefined") {
        var cardDetails = window.holacashCardObject;
        returnInformation.additional_data.cardExpiryMonth =
          cardDetails.cardExpiryMonth;
        returnInformation.additional_data.cardExpiryYear =
          cardDetails.cardExpiryYear;
        returnInformation.additional_data.cardLast4 = cardDetails.cardLast4;
        returnInformation.additional_data.cardBin = cardDetails.cardBin;
      }

      return returnInformation;
    },
  };
  return function (target) {
    return target.extend(mixin);
  };
});
