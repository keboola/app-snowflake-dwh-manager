# Snowflake Data Warehouse Manager

[![Build Status](https://travis-ci.org/keboola/app-snowflake-dwh-manager.svg?branch=master)](https://travis-ci.org/keboola/app-snowflake-dwh-manager)

Used to create business schema, that will be written to by Snowflake writer and that will be read-only shared to a group of analytics. 

## Typical workflow

* you create a schema `accounting` and fill in the data from you accounting software via Keboola Connection's Snowflake Writer
* you create a schema `sales` and fill in the data from you CRM software via Keboola Connection's Snowflake Writer
* you create a user for you analytic `maria@example.com`, that has read-only access to both
* Maria gets access to both accounting and sales as well as her own `maria_example_com` schema (with write access), so that she can combine the data from accounting and sales to deliver an analysis. The data is safe, because she only has read only access to the original data, but in the same time she can do any desctructive changes she wants inside her own schema

# Usage

There are two types of configs - schema config and user config. App detects automatically which is which. 

## schema config
```json
{
    "parameters" : {
        "master_host": "some.snowflakecomputing.com",
        "master_user": "username_that_can_create_roles_and_schemas",
        "#master_password": "password",
        "master_database": "database_that_will_hold_the_schemas",
        "warehouse": "snowflake_warehouse",
        "business_schema": {
            "schema_name": "accounting"
        }
    }
}
```

`schema_name`: the name of the schema to be created, keep it short and simple

## user config
```json
{
    "parameters" : {
        "master_host": "some.snowflakecomputing.com",
        "master_user": "username_that_can_create_roles_and_schemas",
        "#master_password": "password",
        "master_database": "database_that_will_hold_the_schemas",
        "warehouse": "snowflake_warehouse",
        "user": {
           "email": "maria@example.com",
           "business_schemas" : ["accounting", "sales"],
           "disabled": false
       }
    }
}
```

`email`: email address that will be used to log in to the snowflake account
`business_schemas`: array of schema names to be assigned to the new user as read-only
`disabled`: user is not be able to log in while their account is disabled

## Changing access configs

App can be run multiple times with the same config and the result is always the same. It will do it's best to put the resources in expected state. That means:

* it will create missing user schemas or business schemas 
* it will create missing roles if they were deleted by mistake
* it will recreate users
* it will grant privileges to new bussiness schemas to users
* it will revoke business schema privileges from users
* it will change users "disabled" flag accordingly
* it will NOT drop business schemas  
* it will NOT drop users
* it will NOT drop roles

## Master user

Master users needs to be able to create users, schemas and roles.  

```
CREATE DATABASE "DWH_MASTER";

CREATE ROLE "DWH_MASTER";

GRANT OWNERSHIP ON DATABASE "DWH_MASTER" TO ROLE "DWH_MASTER";

CREATE USER "DWH_MASTER"
PASSWORD = "STRONG PASSWORD"
DEFAULT_ROLE = "DWH_MASTER";

GRANT ROLE "DWH_MASTER" TO USER "DWH_MASTER";

GRANT CREATE ROLE ON ACCOUNT TO ROLE "DWH_MASTER" WITH GRANT OPTION;
GRANT CREATE USER ON ACCOUNT TO ROLE "DWH_MASTER" WITH GRANT OPTION;
GRANT USAGE ON WAREHOUSE "DEV" TO ROLE "DWH_MASTER" WITH GRANT OPTION;
```

## Development
 
Clone this repository and init the workspace with following command:

```
git clone https://github.com/keboola/app-snowflake-dwh-manager
cd app-snowflake-dwh-manager
docker-compose build
docker-compose run --rm dev composer install --no-scripts
```

Run the test suite using this command:

```
docker-compose run --rm dev composer ci
```
 
# Integration

For information about deployment and integration with KBC, please refer to the [deployment section of developers documentation](https://developers.keboola.com/extend/component/deployment/) 
