<?php
    /**
     * Created by Tamedo.
     * User: Daniel Rafique
     * Date: 01/09/2014
     * Time: 14:47
     * Copyright all rights reserved to author of this content.
     */

    class Tamedo_Autoshipment_Model_Observer
    {
        public function salesOrderInvoiceShipmentCreate($observer)
        {
            $shipmentCarrierTitle = $shipmentCarrierCode;
            $customerEmailComments = '';
            $order = $observer->getEvent()->getOrder();

            if (!$order->getId()) {
                Mage::throwException("Order does not exist, for the Shipment process to complete");
            }
            try {
                $shipment = Mage::getModel('sales/service_order', $order)->prepareShipment($this->_getItemQtys($order));

                $arrTracking = array(
                    'carrier_code' => isset($shipmentCarrierCode) ? $shipmentCarrierCode : $order->getShippingCarrier()->getCarrierCode(),
                    'title' => isset($shipmentCarrierTitle) ? $shipmentCarrierTitle : $order->getShippingCarrier()->getConfigData('title'),
                    'number' => $shipmentTrackingNumber,
                );
                $track = Mage::getModel('sales/order_shipment_track')->addData($arrTracking);
                $shipment->addTrack($track);
                $shipment->register();
                $this->_saveShipment($shipment, $order, $customerEmailComments);
            }catch (Exception $e) {
                throw $e;
            }
            return $save;
        }
        function _getItemQtys(Mage_Sales_Model_Order $order){
            $qty = array();
            foreach ($order->getAllItems() as $_eachItem) {
                if ($_eachItem->getParentItemId()) {
                    $qty[$_eachItem->getParentItemId()] = $_eachItem->getQtyOrdered();
                } else {
                    $qty[$_eachItem->getId()] = $_eachItem->getQtyOrdered();
                }
            }
            return $qty;
        }

        protected function _saveShipment(Mage_Sales_Model_Order_Shipment $shipment, Mage_Sales_Model_Order $order, $customerEmailComments = '')
        {
            $shipment->getOrder()->setIsInProcess(true);
            Mage::log($shipment->debug(),Zend_Log::INFO,'shipment.log',true);
            $transactionSave = Mage::getModel('core/resource_transaction')
                ->addObject($shipment)
                ->addObject($order)
                ->save();

            $emailSentStatus = $shipment->getData('email_sent');
            if($emailSentStatus)
                Mage::log("Email has been sent",Zend_Log::INFO,'email.log',true);
            else
                Mage::log("IS FALSE",Zend_Log::INFO,'email.log',true);
            if (!is_null($shipment->getOrder()->getCustomerEmail()) && !$emailSentStatus) {
                $shipment->setEmailSent(true);
                $shipment->sendEmail(true, $customerEmailComments);
            }

            return $this;
        }

        protected function _saveOrder(Mage_Sales_Model_Order $order)
        {
            $order->setData('state', Mage_Sales_Model_Order::STATE_COMPLETE);
            $order->setData('status', Mage_Sales_Model_Order::STATE_COMPLETE);
            $order->save();
            return $this;
        }
    }