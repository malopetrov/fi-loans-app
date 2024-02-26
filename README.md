# FI-Loans-app

This Application is to help a Financial Institution (FI) which is handling loans to customers.

- FI ensures access to its loans and customers data to a Debt Information Company (DIC). For this purpose the app provides an API (REST) at ```/debt-information```, where DIC can request the needed info. Authorization is **mutualTLS** with EV certs, handled by the web (apache) server.
- FI is also pushing data to DIC (REST api) when data for the loans is created or updated
- App includes a second API at ```/debt-update``` where other (FI) services can push new or updated data. Authorization is BASIC. And there is a **swagger** documention for the endpoints in ```./public``` folder
- App also provides a command (for a daily run/cronjob) to export data to json files, that can be requested through the ```/debt-information``` API
  
_**NOTE**_: This is not a fully operational application. It is just for preview and demonstration of building a RESTfull API using the **Slim** Miniframework

code: nov 2023