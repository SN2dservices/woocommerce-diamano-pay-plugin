const settings = window.wc.wcSettings.getSetting( 'diamano_pay_data', {} );
const label = window.wp.htmlEntities.decodeEntities( settings.title ) || window.wp.i18n.__( 'My Custom Gateway', 'my_custom_gateway' );

const WC_ASSET_URL = window.wp.htmlEntities.decodeEntities( settings.asset_url );
const Content = () => {
    return window.wp.htmlEntities.decodeEntities( settings.title || '' );
};
const Block_Gateway = {
    name: 'diamano_pay',
    id: 'diamano_pay',
   label: window.wp.element.createElement('img', {src: `${WC_ASSET_URL}/icon.png`, alt: "DiamanoPay", width: 100, height: 100, style: {maxHeight: 100, height: 60} }),
    content: Object( window.wp.element.createElement )( Content, null ),
    edit: Object( window.wp.element.createElement )( Content,null ),
    canMakePayment: () => true,
    ariaLabel: label,
    supports: {
        features: settings.supports,
    },
};
window.wc.wcBlocksRegistry.registerPaymentMethod( Block_Gateway );