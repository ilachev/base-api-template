# Proto PHP Generator

A protoc plugin that generates PHP entity classes, hydrators, and repositories from proto files.

## Installation

1. Clone the repository
2. Install dependencies:
   ```
   composer install
   ```
3. Make the `bin/protoc-php-gen.php` file executable:
   ```
   chmod +x bin/protoc-php-gen.php
   ```

## Usage

First, create your custom options file (options.proto):

```protobuf
syntax = "proto3";

package app.domain;

import "google/protobuf/descriptor.proto";

option php_namespace = "App\\Domain\\Options";

// Custom options for entities
extend google.protobuf.MessageOptions {
  bool is_entity = 50000;
  string table_name = 50001;
  string primary_key = 50002;
}

// Custom options for fields
extend google.protobuf.FieldOptions {
  string db_column = 50100;
  bool is_json = 50101;
  bool ignore = 50102;
}
```

Then add the command to your Taskfile or Makefile:

```
protoc -I=./protos/proto \
  --plugin=protoc-php-gen=./tools/protoc-php-gen/bin/protoc-php-gen.php \
  --php-gen_out=namespace=App\\Gen,output_dir=gen,entity_interface=App\\Domain\\Entity \
  ./protos/proto/app/domain/*.proto
```

## Parameters

- `namespace` - Base namespace for generated classes (default: `App\Gen`)
- `output_dir` - Output directory for generated files (default: `gen`)
- `entity_interface` - Fully qualified class name of the entity interface (default: `App\Domain\Entity`)
- `generate_repositories` - Whether to generate repository classes (default: `true`)
- `generate_hydrators` - Whether to generate hydrator classes (default: `true`)

Example with parameters:

```
protoc -I=./protos/proto \
  --plugin=protoc-php-gen=./tools/protoc-php-gen/bin/protoc-php-gen.php \
  --php-gen_out=namespace=MyApp\\Gen,output_dir=output,entity_interface=MyApp\\Domain\\EntityInterface,generate_repositories=true,generate_hydrators=true \
  ./protos/proto/app/domain/*.proto
```

## Structure of Generated Code

The plugin generates the following classes:

- Entities - in the `{output_dir}/Domain/` directory
- Hydrators - in the `{output_dir}/Infrastructure/Hydrator/` directory
- Repositories - in the `{output_dir}/Infrastructure/Storage/` directory

## Proto3 Features Support

This generator fully supports Proto3 syntax, including:
- All Proto3 scalar types
- Optional fields (using `optional` keyword)
- Repeated fields
- Message types
- Enum types

Note that in Proto3:
- All scalar fields are implicitly optional with default values (0, empty string, false)
- Message fields are always nullable
- There are no required fields
- Default values cannot be specified in the proto file

The generator handles Proto3 specificities:
- Scalar fields are treated as non-nullable in PHP (with default values from Proto3)
- Fields marked with `optional` keyword are explicitly nullable in PHP
- Message fields are always nullable
- Repeated fields are treated as arrays (never null)

## Example Proto File with Additional Options

```protobuf
syntax = "proto3";

package app.domain;

import "google/protobuf/descriptor.proto";

option php_namespace = "App\\Domain\\Session";

extend google.protobuf.MessageOptions {
  bool is_entity = 50000;
  string table_name = 50001;
  string primary_key = 50002;
}

extend google.protobuf.FieldOptions {
  string db_column = 50100;
  bool is_json = 50101;
  bool ignore = 50102;
}

message Session {
  option (app.domain.is_entity) = true;
  option (app.domain.table_name) = "sessions";
  option (app.domain.primary_key) = "id";

  string id = 1;
  optional int64 user_id = 2; // Explicitly optional in Proto3
  string payload = 3 [(app.domain.is_json) = true];
  int64 expires_at = 4;
  int64 created_at = 5;
  int64 updated_at = 6;
  repeated string tags = 7; // Array in PHP
}
```
