# Proto PHP Generator

A protoc plugin that generates PHP entity classes, hydrators, and repositories from proto files.

## Installation

1. Clone the repository
2. Install dependencies:
   ```
   composer install
   ```
3. Make the `bin/protoc-php-gen` file executable:
   ```
   chmod +x bin/protoc-php-gen
   ```

## Usage

Add the command to your Taskfile or Makefile:

```
protoc -I=./protos/proto \
  --plugin=protoc-php-gen=./tools/protoc-php-gen/bin/protoc-php-gen \
  --php-gen_out=gen \
  ./protos/proto/app/domain/*.proto
```

## Parameters

- `namespace` - Base namespace for generated classes (default: `App\Gen`)
- `output_dir` - Output directory for generated files (default: `gen`)
- `entity_interface` - Fully qualified class name of the entity interface (default: `App\Domain\Entity`)

Example with parameters:

```
protoc -I=./protos/proto \
  --plugin=protoc-php-gen=./tools/protoc-php-gen/bin/protoc-php-gen \
  --php-gen_opt=namespace=MyApp\\Gen,output_dir=output,entity_interface=MyApp\\Domain\\EntityInterface \
  --php-gen_out=gen \
  ./protos/proto/app/domain/*.proto
```

## Structure of Generated Code

The plugin generates the following classes:

- Entities - in the `{output_dir}/Domain/` directory
- Hydrators - in the `{output_dir}/Infrastructure/Hydrator/` directory
- Repositories - in the `{output_dir}/Infrastructure/Storage/` directory

## Example Proto File with Additional Options

```protobuf
syntax = "proto3";

package app.domain;

import "google/protobuf/descriptor.proto";

option php_namespace = "App\\Domain\\Session";

extend google.protobuf.MessageOptions {
  optional bool is_entity = 50000 [default = false];
  optional string table_name = 50001;
  optional string primary_key = 50002 [default = "id"];
}

message Session {
  option (is_entity) = true;
  option (table_name) = "sessions";
  option (primary_key) = "id";

  string id = 1;
  optional int64 user_id = 2;
  string payload = 3;
  int64 expires_at = 4;
  int64 created_at = 5;
  int64 updated_at = 6;
}
```
