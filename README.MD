# @storeowner | Magento 2

General-purpose Magento AI 

Neutrome Labs, 2025


## Install in 3 simple steps:

1. Install this extension and activate Cloud

```
composer require neutrome-labs/magento2-mcp
bin/magento module:enable NeutromeLabs_Core NeutromeLabs_Mcp
bin/magento setup:upgrade
```

- Go `Stores -> Configuration -> Neutrome Labs -> Cloud Account` and Sign In

2. Prepare your instance

- Create and prepare Integration Token from `System -> Integrations`
- Check `Stores -> Configuration -> Services -> OAuth` - Allow token-only auth is *Enabled*

3. Connect the AI

- Go `Stores -> Configuration -> Neutrome Labs -> @storeowner`
- Create service deployment, or select one you created earlier
- Save configuration to enable AI
