/*
 * (c) Vadim Pavlov 2020 - 2026
 * Shared test helper: mounts a component with the bootstrap-vue-next
 * components and directives it needs registered globally, mirroring the
 * global registration performed in src/main.js.
 */
import { mount } from '@vue/test-utils'
import { createBootstrap } from 'bootstrap-vue-next'
import * as BVN from 'bootstrap-vue-next'

// Register every exported BComponent globally so single-file components that
// rely on the app-wide registration (see main.js) render in isolation.
const globalComponents = {}
for (const [name, exported] of Object.entries(BVN)) {
  if (/^B[A-Z]/.test(name)) {
    globalComponents[name] = exported
  }
}

// The bootstrap directives (v-b-tooltip, etc.) are registered as no-ops so
// templates that use them mount without error.
const noopDirective = {}
const globalDirectives = {
  'b-tooltip': noopDirective,
  'b-popover': noopDirective,
  'b-toggle': noopDirective,
  'b-modal': noopDirective
}

export function mountWithBootstrap(component, options = {}) {
  const {
    global: globalOverrides = {},
    ...rest
  } = options

  return mount(component, {
    ...rest,
    global: {
      plugins: [createBootstrap(), ...(globalOverrides.plugins || [])],
      components: { ...globalComponents, ...(globalOverrides.components || {}) },
      directives: { ...globalDirectives, ...(globalOverrides.directives || {}) },
      stubs: { ...(globalOverrides.stubs || {}) },
      mocks: { ...(globalOverrides.mocks || {}) },
      provide: { ...(globalOverrides.provide || {}) }
    }
  })
}
