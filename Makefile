PHP ?= php
COMPOSER_BIN ?= composer
CARGO ?= cargo

# OS detection for shared library extension
UNAME_S := $(shell uname -s)
ifeq ($(UNAME_S),Darwin)
    LIB_EXT := dylib
else ifeq ($(UNAME_S),Linux)
    LIB_EXT := so
else
    LIB_EXT := dll
endif

# Colors for help output
YELLOW := $(shell tput setaf 3)
GREEN  := $(shell tput setaf 2)
RESET  := $(shell tput sgr0)

.PHONY: all help install test bench update-core clean

# Default to test
all: test

## Help: Show this help message
help:
	@echo "$(YELLOW)Quill Framework Build System$(RESET)"
	@echo "Usage: make [target]"
	@echo ""
	@echo "$(GREEN)Targets:$(RESET)"
	@grep -E '^[a-zA-Z_-]+:.*?## .*$$' $(MAKEFILE_LIST) | sort | awk 'BEGIN {FS = ":.*?## "}; {printf "  $(YELLOW)%-12s$(RESET) %s\n", $$1, $$2}'

install: ## Install composer dependencies
	$(COMPOSER_BIN) install --optimize-autoloader

test: ## Run Pest test suite
	$(PHP) vendor/bin/pest

bench: ## Run high-performance HTTP benchmarks
	$(PHP) $(PHP_OPTS) bin/quill benchmark

update-core: ## Rebuild native core and sync binary
	$(MAKE) -C ../quill-core build CARGO=$(CARGO)
	$(MAKE) update-core-sync

update-core-sync: ## Sync native binary to local vendor directory
	mkdir -p vendor/quillphp/quill-core/bin/
	cp ../quill-core/target/release/libquill_core.$(LIB_EXT) vendor/quillphp/quill-core/bin/
	cp ../quill-core/quill.h vendor/quillphp/quill-core/bin/

clean: ## Clean local cache artifacts
	rm -rf tmp/cache/*
