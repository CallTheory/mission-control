# Config Editor

The Config Editor utility provides a web interface for editing Amtelco Intelligent Series `sysConfig` and `schSchedule` records directly from Mission Control.

## Access

The Config Editor requires the `utility.config_editor` capability, and must be enabled both as a
system utility and for the team. See [Permissions](../system/permissions.md).

## Features

- Load and decrypt `sysConfig.Config` and `sysConfig.Config2` fields
- Load and decrypt `schSchedule.RecordJSON` by schedule ID
- Edit encrypted XML/JSON configuration values in a web-based editor
- Encrypt and save changes back to the Intelligent Series database

## How It Works

Configuration records in the IS database are stored with DES-EDE3-CBC encryption. The Config Editor decrypts these values for viewing and editing, then re-encrypts them before saving back to the database.

Schedule records include the `schId`, `Scheduled`, and `Action` fields along with the encrypted `RecordJSON` content.

## Setup

Enable the Config Editor in [System Settings](../system/index.md) and ensure the Intelligent Series database connection is configured in [Datasources](../system/datasources.md).
